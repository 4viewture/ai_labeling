CREATE TABLE sys_file_metadata (
    tx_ailabeling_involvement int(11) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_tool varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_real_reference tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_deceptive tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_synthetic_person tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_product_altered tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_public_interest tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_human_review tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_reviewer varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_mode int(11) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_label_text varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_notes text,
    tx_ailabeling_detected tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_detection text,
    tx_ailabeling_status int(11) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_reasons varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_active tinyint(1) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tt_content (
    tx_ailabeling_involvement int(11) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_tool varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_real_reference tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_deceptive tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_synthetic_person tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_product_altered tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_public_interest tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_human_review tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_reviewer varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_mode int(11) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_label_text varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_notes text,
    tx_ailabeling_detected tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_detection text,
    tx_ailabeling_status int(11) unsigned DEFAULT '0' NOT NULL,
    tx_ailabeling_reasons varchar(255) DEFAULT '' NOT NULL,
    tx_ailabeling_active tinyint(1) unsigned DEFAULT '0' NOT NULL
);
