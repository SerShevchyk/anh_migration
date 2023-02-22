<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * @MigrateSource(
 *   id = "node_publication"
 * )
 */

class NodePublication extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('node', 'n')
      ->fields('n', [
        'nid',
        'type',
        'title',
        'uid',
        'status',
        'created',
        'changed',
      ])
      ->condition('n.type', 'publication');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('The primary identifier for a node'),
      'type' => $this->t('The node_type.type of this node'),
      'title' => $this->t('The title of this node, always treated as non-markup plain text'),
      'uid' => $this->t('The users.uid that owns this node; initially, this is the user that created id'),
      'status' => $this->t('Boolean indicating whether the node is published (visible to non-administrators)'),
      'created' => $this->t('The Unix timestamp when the node was created'),
      'changed' => $this->t('The Unix timestamp when the node was most recently saved.'),
      // Custom added fields.
      'field_link' => $this->t('Link'),
      'field_publication_name' => $this->t('Publication name'),
      'field_theme' => $this->t('Theme'),
      'field_year' => $this->t('Year'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');

    // Field link
    $query = $this->select('field_data_field_link','fdfl')
      ->fields('fdfl', ['field_link_url'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_link', $query['field_link_url']);

    // field_publication_name
    $query = $this->select('field_data_field_publication_name','fdfpn')
      ->fields('fdfpn', ['field_publication_name_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_publication_name', $query['field_publication_name_value']);

    // field_theme
    $query = $this->select('field_data_field_theme','fdft')
      ->fields('fdft', ['field_theme_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_theme', $query);

    // field_year
    $query = $this->select('field_data_field_year','fdfy')
      ->fields('fdfy', ['field_year_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_year', $query['field_year_value']);

    return parent::prepareRow($row);
  }

}
