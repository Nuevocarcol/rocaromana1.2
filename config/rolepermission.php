<?php

$config = [
    'dashboard' => array('read'),


    'users_accounts' => array('create', 'read', 'update'),
    'about_us' => array('read','update'),
    'privacy_policy' => array('read','update'),
    'terms_condition' => array('read','update'),
    'customer' => array('create', 'read', 'update','delete'),
    'slider' => array('create', 'read', 'update','delete'),
    'categories' => array('create', 'read', 'update','delete'),
    'type' => array('create', 'read', 'update','delete'),
    'unit' => array('create', 'read', 'update','delete'),
    'property' => array('create', 'read', 'update','delete'),
    'property_inquiry' => array('read', 'update','delete'),
    'notification' => array('read', 'update','delete'),
    'comercial' => array('read', 'update','delete'),
    'facility'              => array('create', 'read', 'update'),
    'categories'            => array('create', 'read', 'update'),
    'near_by_places'        => array('create', 'read', 'update','delete'),
    'customer'              => array('read', 'update','delete'),
    'property'              => array('create', 'read', 'update','delete'),
    'city_images'           => array('read', 'update', 'delete'),
    'project'               => array('create', 'read', 'update','delete'),
    'report_reason'         => array('create', 'read', 'update','delete'),
    'user_reports'          => array('read'),
    'users_inquiries'       => array('read'),
    'chat'                  => array('create', 'read'),
    'slider'                => array('create', 'read', 'update','delete'),
    'article'               => array('create', 'read', 'update','delete'),
    'advertisement'         => array('read', 'update'),
    'package'               => array('create', 'read', 'update','delete'),
    'user_package'          => array('read'),
    'calculator'            => array('read'),
    'payment'               => array('read'),
    'faqs'                  => array('create', 'read', 'update','delete'),
    'users_accounts'        => array('create', 'read', 'update'),
    'about_us'              => array('read','update'),
    'privacy_policy'        => array('read','update'),
    'terms_condition'       => array('read','update'),
    'system_settings'       => array('read', 'update'),
    'notification'          => array('read', 'create','delete'),
    'system_update'         => array('read', 'update'),
];
return $config;
