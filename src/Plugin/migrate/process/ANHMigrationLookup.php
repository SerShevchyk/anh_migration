<?php

namespace Drupal\anh_migration\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateStubInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Skips processing the current row when a destination value does not exist.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "anh_migration_lookup"
 * )
 */

class ANHMigrationLookup extends MigrationLookup implements ContainerFactoryPluginInterface {
  protected $entity = 'node';
  protected $property = 'nid';

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, $migrate_lookup, $migrate_stub = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $migrate_lookup, $migrate_stub);
    if (!$migrate_lookup instanceof MigrateLookupInterface) {
      @trigger_error('Not passing the migrate lookup service as the fifth parameter to ' . __METHOD__ . ' is deprecated in drupal:8.8.0 and will throw a type error in drupal:9.0.0. Pass an instance of \\Drupal\\migrate\\MigrateLookupInterface. See https://www.drupal.org/node/3047268', E_USER_DEPRECATED);
      $migrate_lookup = \Drupal::service('migrate.lookup');
    }
    if (!$migrate_stub instanceof MigrateStubInterface) {
      @trigger_error('Not passing the migrate stub service as the sixth parameter to ' . __METHOD__ . ' is deprecated in drupal:8.8.0 and will throw a type error in drupal:9.0.0. Pass an instance of \\Drupal\\migrate\\MigrateStubInterface. See https://www.drupal.org/node/3047268', E_USER_DEPRECATED);
      $migrate_stub = \Drupal::service('migrate.stub');
    }
    $this->migration = $migration;

    $this->migrateLookup = $migrate_lookup;
    $this->migrateStub = $migrate_stub;

    if (!empty($configuration['entity'])) {
      $this->entity = $configuration['entity'];
    }

    if (!empty($configuration['property'])) {
      $this->property = $configuration['property'];
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateSkipProcessException
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $count = \Drupal::entityQuery($this->entity)
      ->condition($this->property, $value)
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    if (!$count) {
      $message = isset($this->configuration['message']) ? $this->configuration['message'] : '';
      throw new MigrateSkipRowException($message);
    }

    $lookup_migration_ids = (array) $this->configuration['migration'];
    $self = FALSE;
    $destination_ids = NULL;
    $source_id_values = [];
    foreach ($lookup_migration_ids as $lookup_migration_id) {
      if ($lookup_migration_id == $this->migration->id()) {
        $self = TRUE;
      }
      if (isset($this->configuration['source_ids'][$lookup_migration_id])) {
        $value = array_values($row->getMultiple($this->configuration['source_ids'][$lookup_migration_id]));
      }
      if (!is_array($value)) {
        $value = [$value];
      }
      $this->skipInvalid($value);
      $source_id_values[$lookup_migration_id] = $value;

      // Re-throw any PluginException as a MigrateException so the executable
      // can shut down the migration.
      try {
        $destination_id_array = $this->migrateLookup->lookup($lookup_migration_id, $value);
      }
      catch (PluginNotFoundException $e) {
        $destination_id_array = [];
      }
      catch (MigrateException $e) {
        throw $e;
      }
      catch (\Exception $e) {
        throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
      }

      if ($destination_id_array) {
        $destination_ids = array_values(reset($destination_id_array));
        break;
      }
    }

    if (!$destination_ids && !empty($this->configuration['no_stub'])) {
      return NULL;
    }

    if (!$destination_ids && ($self || isset($this->configuration['stub_id']) || count($lookup_migration_ids) == 1)) {
      // If the lookup didn't succeed, figure out which migration will do the
      // stubbing.
      if ($self) {
        $stub_migration = $this->migration->id();
      }
      elseif (isset($this->configuration['stub_id'])) {
        $stub_migration = $this->configuration['stub_id'];
      }
      else {
        $stub_migration = reset($lookup_migration_ids);
      }
      // Rethrow any exception as a MigrateException so the executable can shut
      // down the migration.
      try {
        $destination_ids = $this->migrateStub->createStub($stub_migration, $source_id_values[$stub_migration], [], FALSE);
      }
      catch (\LogicException $e) {
        // For BC reasons, we must allow attempting to stub a derived migration.
      }
      catch (PluginNotFoundException $e) {
        // For BC reasons, we must allow attempting to stub a non-existent
        // migration.
      }
      catch (MigrateException $e) {
        throw $e;
      }
      catch (MigrateSkipRowException $e) {
        throw $e;
      }
      catch (\Exception $e) {
        throw new MigrateException(sprintf('A(n) %s was thrown while attempting to stub.', gettype($e)), $e->getCode(), $e);
      }
    }
    if ($destination_ids) {
      if (count($destination_ids) == 1) {
        return reset($destination_ids);
      }
      else {
        return $destination_ids;
      }
    }
  }
}
