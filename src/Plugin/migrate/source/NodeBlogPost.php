<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * @MigrateSource(
 *   id = "node_blog_post"
 * )
 */
class NodeBlogPost extends SqlBase {

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
      ->condition('n.type', 'wp_blog');
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
      'field_bp_blog_tags' => $this->t('Blog tags'),
      'field_bp_image' => $this->t('Image'),
      'promote' => $this->t('Add slide to home pages'),
      'field_bp_body' => $this->t('Body'),
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

    // field_bp_blog_tags
    $query = $this->select('field_data_taxonomy_wp_blog_tags', 'fdtwbt')
      ->fields('fdtwbt', ['taxonomy_wp_blog_tags_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_bp_blog_tags', $query);

    // field_bp_image
    $query = $this->select('field_data_field_slide_image', 'fdfsi')
      ->fields('fdfsi', ['field_slide_image_fid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_bp_image', $query['field_slide_image_fid']);

    // field_bp_slide_home_pages
    $query = $this->select('field_data_field_add_slide_to_home_page', 'fdfasthp')
      ->fields('fdfasthp', ['field_add_slide_to_home_page_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    if (!empty($query)) {
      $row->setSourceProperty('promote', '1');
    }

    // field_bp_body
    $query = $this->select('field_data_body', 'fdb')
      ->fields('fdb', ['body_value', 'body_summary', 'body_format'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $body = $query['body_value'];
    while (preg_match('/\[\[.*]]/', $body, $matches)) {
      $matches  = array_shift($matches);
      $image = json_decode($matches);
      $image = $image[0][0];
      $query = $this->select('file_managed', 'fm')
        ->fields('fm')
        ->condition('fid', $image->fid)
        ->execute()
        ->fetch();
      $uri = $query['uri'];
      $src = $uri ? 'src="' . str_replace('public://', '/sites/default/files/', $uri) . '"' : NULL;

      $class = $image->attributes->class;
      $class = $class ? 'class="' . $class . '"' : NULL;

      $style = $image->attributes->style;
      $style = $style ? 'style="' . $style . '"' : NULL;

      $html = "<img $class $src $style />";
      $body = preg_replace('/\[\[.*]]/', $html, $body, '1' );
    }
    $row->setSourceProperty('field_bp_body_value', $body);
    $row->setSourceProperty('field_bp_body_summary', $query['body_summary']);
    $row->setSourceProperty('field_bp_body_format', 'full_html');

    return parent::prepareRow($row);
  }

}
