<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 field_collection_item source from database
 *
 * @MigrateSource(
 *   id = "anh_field_collection"
 * )
 */
class FieldCollection extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Select node in its last revision.
    $query = $this->select('field_collection_item', 'fci')->fields('fci', [
      'item_id',
      'field_name',
      'revision_id',
    ]);
    if (isset($this->configuration['field_name'])) {
      $query->innerJoin('field_data_' . $this->configuration['field_name'], 'fd', 'fd.' . $this->configuration['field_name'] . '_value = fci.item_id');
      $query->fields('fd', [
        'entity_type',
        'bundle',
        'entity_id',
        $this->configuration['field_name'] . '_revision_id',
      ]);
      $query->condition('fci.field_name', $this->configuration['field_name']);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'item_id' => $this->t('Item ID'),
      'revision_id' => $this->t('Revision ID'),
      'field_name' => $this->t('Name of field'),
      'field_institutions_involved' => $this->t('Institutions involved'),
      'field_investigators' => $this->t('Investigators'),
      'field_main_methods_used' => $this->t('Main methods used'),
      'field_rp_project_name' => $this->t('Project name'),
      'field_project_website' => $this->t('Project website'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // If field specified, get field revision ID so there aren't issues mapping.
    if (isset($this->configuration['field_name'])) {
      $row->setSourceProperty('revision_id', $row->getSourceProperty($this->configuration['field_name'] . '_revision_id'));
    }

    // Get field API field values.
    foreach (array_keys($this->getFields('field_collection_item', $row->getSourceProperty('field_name'))) as $field) {
      $item_id = $row->getSourceProperty('item_id');
      $row->setSourceProperty($field, $this->getFieldValues('field_collection_item', $field, $item_id, NULL));
    }
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['item_id']['type'] = 'integer';
    $ids['item_id']['alias'] = 'fci';
    return $ids;
  }

  /**
   * Retrieves field values for a single field of a single entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field
   *   The field name.
   * @param int $entity_id
   *   The entity ID.
   * @param int|null $revision_id
   *   (optional) The entity revision ID.
   * @param string $language
   *   (optional) The field language.
   *
   * @return array
   *   The raw field values, keyed by delta.
   */
  protected function getFieldCollectionValues($entity_type, $field, $entity_id, $revision_id = NULL, $language = NULL) {
    $table = (isset($revision_id) ? 'field_revision_' : 'field_data_') . $field;
    $query = $this->select($table, 't')
      ->fields('t')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('deleted', 0);
    if (isset($revision_id)) {
      $query->condition('revision_id', $revision_id);
    }
    // Add 'language' as a query condition if it has been defined by Entity
    // Translation.
    if ($language) {
      $query->condition('language', $language);
    }
    $values = [];
    foreach ($query->execute() as $row) {
      foreach ($row as $key => $value) {
        $delta = $row['delta'];
        if (strpos($key, $field) === 0) {
          $column = substr($key, strlen($field) + 1);
          $values[$delta][$column] = $value;
        }
      }
    }
    return $values;
  }

}
