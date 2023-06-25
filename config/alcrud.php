<?php

use AbuDawud\AlCrudLaravel\Models\BaseModel;

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'parent_model' => BaseModel::class,
    'user_model' => 'App\Models\User',
    'controller' => 'App\Http\Controllers\Controller.php',
    'route_model' => 'App\Models\Sys\Route',
    'menu_model' => 'App\Models\Sys\Menu',
    'default_module_id' => 1,
    'default_role_id' => 1,
];
