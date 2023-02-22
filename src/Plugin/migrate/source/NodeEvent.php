<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * @MigrateSource(
 *   id = "node_event"
 * )
 */

class NodeEvent extends SqlBase {

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
      ->condition('n.type', ['event', 'academy_event', 'immana_event'], 'IN');
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
      'promote' => $this->t('Add page to home page'),
      'field_e_event_description' => $this->t('Event description'),
      'field_e_content_type' => $this->t('Content type'),
      'field_e_date_and_time' => $this->t('Start Date'),
      'field_e_event_type' => $this->t('Event type'),
      'field_e_location' => $this->t('Location'),
      'field_e_slide_summary' => $this->t('field_slide_summary'),
      'field_e_speaker' => $this->t('Speaker'),
      'field_e_speaker_bio' => $this->t('Speaker bio'),
      'field_event_partner_organisation' => $this->t('Is this event run by the Academy or a partner organisation'),
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

    // field_event_content_type
    $query = $this->select('field_data_field_content_type','fdfct')
      ->fields('fdfct', ['field_content_type_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_event_content_type', $query);

    // field_event_type
    $query = $this->select('field_data_field_event_type','fdfet')
      ->fields('fdfet', ['field_event_type_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_event_type', $query['field_event_type_tid']);

    // field_event_image
    $query = $this->select('field_data_field_slide_image','fdfsi')
      ->fields('fdfsi', ['field_slide_image_fid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_event_image', $query['field_slide_image_fid']);

    // field_event_slide_summary
    $query = $this->select('field_data_field_slide_summary','fdfss')
      ->fields('fdfss', ['field_slide_summary_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_event_slide_summary', $query['field_slide_summary_value']);

    // field_event_slide_home_pages
    $query = $this->select('field_data_field_add_slide_to_home_page','fdfasthp')
      ->fields('fdfasthp', ['field_add_slide_to_home_page_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    if (!empty($query)) {
      $row->setSourceProperty('promote', '1');
    }

    // field_event_date_and_time
    $query = $this->select('field_data_field_startdate','fdfs')
      ->fields('fdfs', ['field_startdate_value', 'field_startdate_value2'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_event_date_and_time_value', date('Y-m-d\TH:i:00', $query['field_startdate_value']));
    $row->setSourceProperty('field_event_date_and_time_end_value', date('Y-m-d\TH:i:00', $query['field_startdate_value2']));

    // field_event_location
    $query = $this->select('field_data_field_location','fdfl')
      ->fields('fdfl', ['field_location_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_event_location', $query['field_location_value']);

    // field_event_speaker
    $query = $this->select('field_data_field_speaker','fdfs')
      ->fields('fdfs', ['field_speaker_value', 'field_speaker_format'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $body = $query['field_speaker_value'];
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
    $row->setSourceProperty('field_event_speaker_value', $body);
    $row->setSourceProperty('field_event_speaker_format', 'full_html');

    // field_event_speaker_bio
    $query = $this->select('field_data_field_speaker_bio','fdfsb')
      ->fields('fdfsb', ['field_speaker_bio_value', 'field_speaker_bio_format'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $body = $query['field_speaker_bio_value'];
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
    $row->setSourceProperty('field_event_speaker_bio_value', $body);
    $row->setSourceProperty('field_event_speaker_bio_format', 'full_html');

    // field_event_description
    $query = $this->select('field_data_body','fdb')
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
    $row->setSourceProperty('field_event_description_value', $body);
    $row->setSourceProperty('field_event_description_summary', $query['body_summary']);
    $row->setSourceProperty('field_event_description_format', 'full_html');

    // field_event_file_attachments
    $query = $this->select('field_data_upload','fdu')
      ->fields('fdu', ['upload_fid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('field_event_file_attachments', $query);

    // field_event_partner_organisation
    $query = $this->select('field_data_field_wo_is_this_event_run_by','fdfwiterb')
      ->fields('fdfwiterb', ['field_wo_is_this_event_run_by_tid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_event_partner_organisation', $query['field_wo_is_this_event_run_by_tid']);

    // field_academy
    $query = $this->select('domain_access', 'da')
      ->fields('da', ['gid'])
      ->condition('nid', $nid)
      ->execute()
      ->fetchCol();
    $result = [];
    foreach ($query as $item) {
      switch ($item) {
        case '1':
          break;

        case '2':
          $result[] = '2';
          break;

        case '3':
          $result[] = '1';
          break;
      }
    }
    $row->setSourceProperty('field_academy', $result);

    return parent::prepareRow($row);
  }

}
