<?php

namespace Emails;

class EmailsMigration extends \Db\Migration {

  /**
   * Apply only the current migration.
   * @return true on success or false on failure
   */
  function apply(\Db\Connection $db) {
    $q = $db->prepare("CREATE TABLE emails (
      id int not null auto_increment primary key,
      created_at timestamp not null default current_timestamp,

      user_id int null,
      to_name varchar(255) null,
      to_email varchar(255) null,
      subject varchar(255) null,
      template_id varchar(255) null,
      arguments blob null,

      INDEX(user_id),
      INDEX(template_id)
    );");
    return $q->execute();
  }

}
