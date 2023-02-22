<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\Core\Locale\CountryManager;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 user source from database.
 *
 * @MigrateSource(
 *   id = "user"
 * )
 */
class User extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u')
      ->fields('u')
      ->condition('u.uid', 0, '>')
      ->orderBy('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'uid' => $this->t('User ID'),
      'name' => $this->t('Username'),
      'pass' => $this->t('Password'),
      'mail' => $this->t('Email address'),
      'signature' => $this->t('Signature'),
      'signature_format' => $this->t('Signature format'),
      'created' => $this->t('Registered timestamp'),
      'access' => $this->t('Last access timestamp'),
      'login' => $this->t('Last login timestamp'),
      'status' => $this->t('Status'),
      'timezone' => $this->t('Timezone'),
      'language' => $this->t('Language'),
      'picture' => $this->t('Picture'),
      'init' => $this->t('Init'),
      'data' => $this->t('User data'),
      'roles' => $this->t('Roles'),
      'field_area_of_expertise' => $this->t('Area of expertise'),
      'field_country_of_current' => $this->t('Country of current'),
      'field_training_interests_' => $this->t('Training interests'),
      'field_first_name' =>$this->t('First name'),
      'field_job_title' =>$this->t('Job Title'),
      'field_last_name' =>$this->t('Last Name'),
      'field_nationality_terms' => $this->t('Nationality'),
      'field_organisation_name' =>$this->t('Organisation Name'),
      'field_please_briefly_introduce_' => $this->t(''),
      'field_please_list_your_recent_la' => $this->t(''),
      'field_salutation_title' =>$this->t('Salutation Title'),
      'field_alternative_email_address' =>$this->t('Alternative email address'),
      'field_website_address' =>$this->t('Website address'),
      'field_user_disciplinaryexpertise' =>$this->t('Disciplinary expertise'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $uid = $row->getSourceProperty('uid');

    $roles = $this->select('users_roles', 'ur')
      ->fields('ur', ['rid'])
      ->condition('ur.uid', $uid)
      ->execute()
      ->fetchCol();
    $row->setSourceProperty('roles', $roles);

    $row->setSourceProperty('data', unserialize($row->getSourceProperty('data')));

    $entity_translatable = $this->isEntityTranslatable('user');
    $source_language = $this->getEntityTranslationSourceLanguage('user', $uid);
    $language = $entity_translatable && $source_language ? $source_language : $row->getSourceProperty('language');
    $row->setSourceProperty('entity_language', $language);

    foreach ($this->getFields('user') as $field_name => $field) {
      // Ensure we're using the right language if the entity and the field are
      // translatable.
      $field_language = $entity_translatable && $field['translatable'] ? $language : NULL;
      $row->setSourceProperty($field_name, $this->getFieldValues('user', $field_name, $uid, NULL, $field_language));
    }

    $profile_id = $this->select('profile', 'p')
      ->fields('p', ['pid'])
      ->condition('p.uid', $uid)
      ->execute()
      ->fetchCol();

    if (!empty($profile_id)) {
      // field_user_salutation_title
      $query = $this->select('field_data_field_salutation_title','fdfst')
        ->fields('fdfst', ['field_salutation_title_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_salutation_title', $query['field_salutation_title_value']);

      // field_user_first_name
      $query = $this->select('field_data_field_first_name','fdffn')
        ->fields('fdffn', ['field_first_name_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_first_name', $query['field_first_name_value']);

      // field_user_last_name
      $query = $this->select('field_data_field_last_name','fdfln')
        ->fields('fdfln', ['field_last_name_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $lastName = $query['field_last_name_value'];
      $row->setSourceProperty('field_user_last_name', $lastName);

      // field_user_organisation_name
      $query = $this->select('field_data_field_organisation_name','fdfon')
        ->fields('fdfon', ['field_organisation_name_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_organisation_name', $query['field_organisation_name_value']);

      // field_user_job_title
      $query = $this->select('field_data_field_job_title','fdfjt')
        ->fields('fdfjt', ['field_job_title_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_job_title', $query['field_job_title_value']);

      // field_user_scientific_title
      $query = $this->select('field_data_field_scientific_title_','fdfst')
        ->fields('fdfst', ['field_scientific_title__value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_scientific_title', $query['field_scientific_title__value']);

      // field_user_nationality
      $tids = $this->select('field_data_field_nationality_terms','fdfnt')
        ->fields('fdfnt', ['field_nationality_terms_tid'])
        ->condition('entity_id', $profile_id)
        ->condition('delta', 1)
        ->execute()
        ->fetch();
      if (isset($tids["field_nationality_terms_tid"]) && !empty($tids["field_nationality_terms_tid"])) {
        $term = $this->select('taxonomy_term_data','ttd')
          ->fields('ttd', ['name'])
          ->condition('tid', $tids["field_nationality_terms_tid"])
          ->execute()
          ->fetch();
        $row->setSourceProperty('field_user_nationality', $term['name']);
      }
      else {
        $row->setSourceProperty('field_user_nationality', "");
      }

      // field_user_disciplinaryexpertise
      $tids = [];
      $results = $this->select('field_data_field_training_interests_','fdfti')
        ->fields('fdfti', ['field_training_interests__tid'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetchAll();
      foreach ($results as $item) {
        $tids[] = $item["field_training_interests__tid"];
      }
      $row->setSourceProperty('field_training_interests_', $tids);

      // field_sectoral_expertise
      $tids = [];
      $results = $this->select('field_data_field_sectoral_expertise','fdfse')
        ->fields('fdfse', ['field_sectoral_expertise_tid'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetchAll();
      foreach ($results as $item) {
        $tids[] = $item["field_sectoral_expertise_tid"];
      }
      $row->setSourceProperty('field_sectoral_expertise', $tids);

      // field_area_of_expertise
      $tids = [];
      $results = $this->select('field_data_field_area_of_expertise','fdfaoe')
        ->fields('fdfaoe', ['field_area_of_expertise_tid'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetchAll();
      foreach ($results as $item) {
        $tids[] = $item["field_area_of_expertise_tid"];
      }
      $row->setSourceProperty('field_area_of_expertise', $tids);

      // field_alternative_email_address
      $query = $this->select('field_data_field_alternative_email_address','fdaea')
        ->fields('fdaea', ['field_alternative_email_address_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      if ($query && isset($query['field_alternative_email_address_value'])) {
        $row->setSourceProperty('field_alternative_email_address', $query['field_alternative_email_address_value']);
      }

      // field_website_address
      $query = $this->select('field_data_field_website_address','fdfwa')
        ->fields('fdfwa', ['field_website_address_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      if ($query && isset($query['field_website_address_value'])) {
        $row->setSourceProperty('field_website_address', $query['field_website_address_value']);
      }

      // field_user_photo
      $photo_id = $this->select('field_data_field_personal_profile_photo_opt','fdfpppo')
        ->fields('fdfpppo', ['field_personal_profile_photo_opt_fid'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $query = $this->select('file_managed', 'fm')
        ->fields('fm')
        ->condition('fid', $photo_id['field_personal_profile_photo_opt_fid'])
        ->execute()
        ->fetch();
      $path = str_replace('public://', '/var/www/anh-academy/web/sites/default/files/source/', $query['uri']);
      if (file_exists($path)) {
        $row->setSourceProperty('personal_profile_photo_source', $path);
        $row->setSourceProperty('personal_profile_photo_destination', $query['uri']);
        $row->setSourceProperty('personal_profile_photo_uid', 1);
      }

      // field_user_introduce_yourself
      $query = $this->select('field_data_field__please_briefly_introduce_','fdfpbi')
        ->fields('fdfpbi', ['field__please_briefly_introduce__value', 'field__please_briefly_introduce__format'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      if (isset($query['field__please_briefly_introduce__value'])) {
        $row->setSourceProperty('field_user_introduce_yourself_value', html_entity_decode(strip_tags($query['field__please_briefly_introduce__value'])));
      }

      // field_please_list_your_recent_la
      $query = $this->select('field_data_field_please_list_your_recent_la','fdfplyrl')
        ->fields('fdfplyrl', ['field_please_list_your_recent_la_value', 'field_please_list_your_recent_la_format'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      if (isset($query["field_please_list_your_recent_la_value"])) {
        $row->setSourceProperty('field_please_list_your_recent_la', html_entity_decode(strip_tags($query['field_please_list_your_recent_la_value'])));
      }

      // field_user_data_protection
      $query = $this->select('field_data_field_data_protection','fdfdp')
        ->fields('fdfdp', ['field_data_protection_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_data_protection', $query['field_data_protection_value']);

      // field_user_allow_contact
      $query = $this->select('field_data_field_i_agree_for_my_contact_det','fdfiafmcd')
        ->fields('fdfiafmcd', ['field_i_agree_for_my_contact_det_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_allow_contact', $query['field_i_agree_for_my_contact_det_value']);

      // field_user_visible_member
      $query = $this->select('field_data_field_i_agree_to_my_name_photo_a','fdfiatmnpa')
        ->fields('fdfiatmnpa', ['field_i_agree_to_my_name_photo_a_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_visible_member', $query['field_i_agree_to_my_name_photo_a_value']);

      // field_user_used_info
      $query = $this->select('field_data_field_i_agree_to_my_name_photo_','fdfiatmnp')
        ->fields('fdfiatmnp', ['field_i_agree_to_my_name_photo__value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetch();
      $row->setSourceProperty('field_user_used_info', $query['field_i_agree_to_my_name_photo__value']);


      // field_user_address/country_code
      $tids = $this->select('field_data_field_country_of_current','fdfnt')
        ->fields('fdfnt', ['field_country_of_current_tid'])
        ->condition('entity_id', $profile_id)
        ->condition('delta', 1)
        ->execute()
        ->fetch();
      if (isset($tids["field_country_of_current_tid"]) && !empty($tids["field_country_of_current_tid"])) {
        $term = $this->select('taxonomy_term_data','ttd')
          ->fields('ttd', ['name'])
          ->condition('tid', $tids["field_country_of_current_tid"])
          ->execute()
          ->fetch();

        $countries = CountryManager::getStandardList();
        foreach ($countries as $key => $value) {
          $value = $value->render();
          if ($value == $term['name']) {
            $row->setSourceProperty('field_user_address', $key);
            break;
          }
        }
      }
      else {
        $row->setSourceProperty('field_user_address', "");
      }

      // field_your_projects
      $results = $this->select('field_data_field_your_projects', 'fdfyp')
        ->fields('fdfyp', ['field_your_projects_value'])
        ->condition('entity_id', $profile_id)
        ->execute()
        ->fetchAll();
      $values = NULL;
      if ($results && !empty($results)) {
        foreach ($results as $result) {
          $values[] = ['item_id' => $result["field_your_projects_value"]];
        }
      }
      $row->setSourceProperty('field_your_projects', $values);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'uid' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  }

}
