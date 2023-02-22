<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * @MigrateSource(
 *   id = "node_project"
 * )
 */

class NodeProject extends SqlBase {

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
      ->condition('n.type', 'project');
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
      'field_project_theme' => $this->t('Theme'),
      'field_project_region' => $this->t('Region'),
      'field_project_college' => $this->t('College'),
      'field_project_researchers' => $this->t('Researchers'),
      'field_project_status' => $this->t('Project status'),
      'field_project_body' => $this->t('Body'),
      'field_project_link' => $this->t('Link'),
      'field_project_attachment' => $this->t('Attachment')
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

    // field_project_theme
    $query = $this->select('field_data_field_theme','fdft')
      ->fields('fdft', ['field_theme_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_project_theme', $query);

    // field_project_region
    $query = $this->select('field_data_field_region','fdfr')
      ->fields('fdfr', ['field_region_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_project_region', $query['field_region_tid']);

    // field_project_college
    $query = $this->select('field_data_field_college','fdfc')
      ->fields('fdfc', ['field_college_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_project_college', $query);

    // field_project_researchers
    $query = $this->select('field_data_field_researchers','fdfr')
      ->fields('fdfr', ['field_researchers_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_project_researchers', $query);

    // field_project_status
    $query = $this->select('field_data_field_project_status','fdfps')
      ->fields('fdfps', ['field_project_status_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_project_status', $query['field_project_status_value']);

    // field_project_body
    $query = $this->select('field_data_body','fdb')
      ->fields('fdb', ['body_value', 'body_summary', 'body_format'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_project_body_value', $query['body_value']);
    $row->setSourceProperty('field_project_body_summary', $query['body_summary']);
    $row->setSourceProperty('field_project_body_format', 'full_html');

    // field_project_link
    $query = $this->select('field_data_field_link','fdfl')
      ->fields('fdfl', ['field_link_url'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_project_link', $query['field_link_url']);

    // field_project_attachment
    $query = $this->select('field_data_field_attachment','fdfa')
      ->fields('fdfa', ['field_attachment_fid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_project_attachment', $query);

    return parent::prepareRow($row);
  }

}
