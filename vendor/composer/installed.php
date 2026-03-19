<?php return array(
    'root' => array(
        'name' => 'fuel/auth',
        'pretty_version' => 'dev-develop',
        'version' => 'dev-develop',
        'reference' => '085abf0ecd598fd4b722e9944f48acdffeb22780',
        'type' => 'fuel-package',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'composer/installers' => array(
            'pretty_version' => 'v1.12.0',
            'version' => '1.12.0.0',
            'reference' => 'd20a64ed3c94748397ff5973488761b22f6d3f19',
            'type' => 'composer-plugin',
            'install_path' => __DIR__ . '/./installers',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'fuel/auth' => array(
            'pretty_version' => 'dev-develop',
            'version' => 'dev-develop',
            'reference' => '085abf0ecd598fd4b722e9944f48acdffeb22780',
            'type' => 'fuel-package',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'roundcube/plugin-installer' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'shama/baton' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
    ),
);
