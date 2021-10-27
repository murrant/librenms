<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Auth
Auth::routes(['register' => false, 'reset' => false, 'verify' => false]);

Route::get('/flasher', function () {
    // TODO remove testing code :D
    flash()->addWarning('Warning : this is a longer message blah <a href="https://docs.librenms.org">docs</a> ajsdlfkjsdf');
    flash()->addSuccess('Success');
    flash()->addError('Error');
    flash()->addInfo('Info');

    flash()
        ->using('template.librenms')
        ->title('Title')
        ->options(['timeout' => 0])
        ->addSuccess('Toastr');

    return view(['template' => <<<'BLADE'
@extends('layouts.librenmsv1')

@section('title', __('Flasher Test'))

@section('content')
Flasher Test
<style>
#flasher-container-top-right {
    position: fixed;
    z-index: 999999;
    top: 55px;
    right: 12px;
}

#flasher-container-top-right a {
    font-weight: bold;
}

#flasher-container-top-right > div {
    width: 304px;
    min-height: 50px;
    background-position: 10px center;
    background-repeat: no-repeat;
}

.flasher-warning {
    background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PHBhdGggZD0iTTE2IDBBMTYgMTYgMCAwIDAgMCAxNmExNiAxNiAwIDAgMCAxNiAxNiAxNiAxNiAwIDAgMCAxNi0xNkExNiAxNiAwIDAgMCAxNiAwem0wIDYuMTU2YzEuMDE2IDAgMi4wMzIuNDkgMi41OTggMS40NjlsNi45MjcgMTJjMS4xMzEgMS45NTgtLjMzNiA0LjUtMi41OTcgNC41SDkuMDcyYy0yLjI2MSAwLTMuNzI4LTIuNTQyLTIuNTk3LTQuNWw2LjkyNy0xMmMuNTY2LS45NzkgMS41ODItMS40NjkgMi41OTgtMS40Njl6bTAgMS45MzhjLS4zMyAwLS42Ni4xNzctLjg2NS41MzFsLTYuOTMgMTJjLS40MDkuNzA4LjA0OSAxLjUuODY3IDEuNWgxMy44NTZjLjgxOCAwIDEuMjc2LS43OTIuODY3LTEuNWwtNi45My0xMmMtLjIwNC0uMzU0LS41MzQtLjUzMS0uODY1LS41MzF6bTAgNC4wMzFhMSAxIDAgMCAxIDEgMXYyYTEgMSAwIDAgMS0xIDEgMSAxIDAgMCAxLTEtMXYtMmExIDEgMCAwIDEgMS0xem0wIDZoLjAxYTEgMSAwIDAgMSAxIDEgMSAxIDAgMCAxLTEgMUgxNmExIDEgMCAwIDEtMS0xIDEgMSAwIDAgMSAxLTF6IiBmaWxsPSIjZDk3NzA2Ii8+PC9zdmc+");
    background-size: 32px;
}
.flasher-error {
    background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PHBhdGggZD0iTTE2IDBBMTYgMTYgMCAwIDAgMCAxNmExNiAxNiAwIDAgMCAxNiAxNiAxNiAxNiAwIDAgMCAxNi0xNkExNiAxNiAwIDAgMCAxNiAwem0tNiA5YTEgMSAwIDAgMSAuNzA3LjI5M0wxNiAxNC41ODZsNS4yOTMtNS4yOTNhMSAxIDAgMCAxIDEuNDE0IDAgMSAxIDAgMCAxIDAgMS40MTRMMTcuNDE0IDE2bDUuMjkzIDUuMjkzYTEgMSAwIDAgMSAwIDEuNDE0IDEgMSAwIDAgMS0xLjQxNCAwTDE2IDE3LjQxNGwtNS4yOTMgNS4yOTNhMSAxIDAgMCAxLTEuNDE0IDAgMSAxIDAgMCAxIDAtMS40MTRMMTQuNTg2IDE2bC01LjI5My01LjI5M2ExIDEgMCAwIDEgMC0xLjQxNEExIDEgMCAwIDEgMTAgOXoiIGZpbGw9IiNmODcxNzEiIC8+PC9zdmc+");
    background-size: 32px;
}
.flasher-info {
    background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PHBhdGggZD0iTTE2IDBBMTYgMTYgMCAwIDAgMCAxNmExNiAxNiAwIDAgMCAxNiAxNiAxNiAxNiAwIDAgMCAxNi0xNkExNiAxNiAwIDAgMCAxNiAwem0wIDZjNS41MTEgMCAxMCA0LjQ4OSAxMCAxMHMtNC40ODkgMTAtMTAgMTBTNiAyMS41MTEgNiAxNiAxMC40ODkgNiAxNiA2em0wIDJjLTQuNDMgMC04IDMuNTctOCA4czMuNTcgOCA4IDggOC0zLjU3IDgtOC0zLjU3LTgtOC04em0wIDNhMSAxIDAgMCAxIDEgMXY0YTEgMSAwIDAgMS0xIDEgMSAxIDAgMCAxLTEtMXYtNGExIDEgMCAwIDEgMS0xem0wIDhoLjAxYTEgMSAwIDAgMSAxIDEgMSAxIDAgMCAxLTEgMUgxNmExIDEgMCAwIDEtMS0xIDEgMSAwIDAgMSAxLTF6IiBmaWxsPSIjMjU2M2ViIiAvPjwvc3ZnPg==");
    background-size: 32px;
}
.flasher-success {
    background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PHBhdGggZD0iTTE2IDBBMTYgMTYgMCAwIDAgMCAxNmExNiAxNiAwIDAgMCAxNiAxNiAxNiAxNiAwIDAgMCAxNi0xNkExNiAxNiAwIDAgMCAxNiAwem03IDEwYTEgMSAwIDAgMSAuNzA3LjI5MyAxIDEgMCAwIDEgMCAxLjQxNGwtMTAgMTBhMSAxIDAgMCAxLTEuNDE0IDBsLTQtNGExIDEgMCAwIDEgMC0xLjQxNCAxIDEgMCAwIDEgMS40MTQgMEwxMyAxOS41ODZsOS4yOTMtOS4yOTNBMSAxIDAgMCAxIDIzIDEweiIgZmlsbD0iIzA1OTY2OSIgLz48L3N2Zz4=");
    background-size: 32px;
}

/*#flasher-container-top-left.toast-top-full-width > div, #flasher-container-top-left.toast-bottom-full-width > div {*/
/*    width: 96%;*/
/*    margin: auto*/
/*}*/

/*@media all and (max-width: 240px) {*/
/*    #flasher-container-top-left > div {*/
/*        padding: 8px 8px 8px 50px;*/
/*        width: 11em*/
/*    }*/

/*    #flasher-container-top-left .toast-close-button {*/
/*        right: -0.2em;*/
/*        top: -0.2em*/
/*    }*/
/*}*/

/*@media all and (min-width: 241px) and (max-width: 480px) {*/
/*    #flasher-container-top-left > div {*/
/*        padding: 8px 8px 8px 50px;*/
/*        width: 18em*/
/*    }*/

/*    #flasher-container-top-left .toast-close-button {*/
/*        right: -0.2em;*/
/*        top: -0.2em*/
/*    }*/
/*}*/

/*@media all and (min-width: 481px) and (max-width: 768px) {*/
/*    #flasher-container-top-left > div {*/
/*        padding: 15px 15px 15px 50px;*/
/*        width: 25em*/
/*    }*/
/*}*/

body {
 background: lightgray;
}

</style>
<script>
toastr.options = {
    toastClass: 'tw-pl-20 tw-py-4 tw-pr-2 tw-bg-white tw-opacity-80 hover:tw-opacity-100 tw-rounded-md tw-shadow-lg hover:tw-shadow-xl tw-border-l-8 tw-mt-2 tw-cursor-pointer',
    titleClass: 'tw-text-xl tw-leading-7 tw-font-semibold tw-capitalize',
    messageClass: 'tw-mt-1 tw-text-base tw-leading-5 tw-text-gray-500',
    iconClasses: {
        error: 'flasher-error tw-text-red-600 tw-border-red-600',
        info: 'flasher-info tw-text-blue-600 tw-border-blue-600',
        success: 'flasher-success tw-text-green-600 tw-border-green-600',
        warning: 'flasher-warning tw-text-yellow-600 tw-border-yellow-600'
    },
    timeOut: 8000,
    containerId: 'flasher-container-top-right'
};

toastr.success('Test Message', 'Success Title', {timeOut: 0})
toastr.error('Test Message', null, {timeOut: 0})
toastr.info('Test Message', 'Info')
toastr.warning('Warning : this is a longer message blah <a href="https://docs.librenms.org">docs</a> ajsdlfkjsdf', 'Title', {timeOut: 0})

</script>
@endsection

BLADE
]);

});

// WebUI
Route::group(['middleware' => ['auth'], 'guard' => 'auth'], function () {

    // pages
    Route::post('alert/{alert}/ack', [\App\Http\Controllers\AlertController::class, 'ack'])->name('alert.ack');
    Route::resource('device-groups', 'DeviceGroupController');
    Route::resource('port-groups', 'PortGroupController');
    Route::resource('port', 'PortController', ['only' => 'update']);
    Route::group(['prefix' => 'poller'], function () {
        Route::get('', 'PollerController@pollerTab')->name('poller.index');
        Route::get('log', 'PollerController@logTab')->name('poller.log');
        Route::get('groups', 'PollerController@groupsTab')->name('poller.groups');
        Route::get('settings', 'PollerController@settingsTab')->name('poller.settings');
        Route::get('performance', 'PollerController@performanceTab')->name('poller.performance');
        Route::resource('{id}/settings', 'PollerSettingsController', ['as' => 'poller'])->only(['update', 'destroy']);
    });
    Route::prefix('services')->name('services.')->group(function () {
        Route::resource('templates', 'ServiceTemplateController');
        Route::post('templates/applyAll', 'ServiceTemplateController@applyAll')->name('templates.applyAll');
        Route::post('templates/apply/{template}', 'ServiceTemplateController@apply')->name('templates.apply');
        Route::post('templates/remove/{template}', 'ServiceTemplateController@remove')->name('templates.remove');
    });
    Route::get('locations', 'LocationController@index');
    Route::resource('preferences', 'UserPreferencesController', ['only' => ['index', 'store']]);
    Route::resource('users', 'UserController');
    Route::get('about', 'AboutController@index');
    Route::get('authlog', 'UserController@authlog');
    Route::get('overview', 'OverviewController@index')->name('overview');
    Route::get('/', 'OverviewController@index')->name('home');
    Route::view('vminfo', 'vminfo');

    // Device Tabs
    Route::group(['prefix' => 'device/{device}', 'namespace' => 'Device\Tabs', 'as' => 'device.'], function () {
        Route::put('notes', 'NotesController@update')->name('notes.update');
    });

    Route::match(['get', 'post'], 'device/{device}/{tab?}/{vars?}', 'DeviceController@index')
        ->name('device')->where(['vars' => '.*']);

    // Maps
    Route::group(['prefix' => 'maps', 'namespace' => 'Maps'], function () {
        Route::get('devicedependency', 'DeviceDependencyController@dependencyMap');
    });

    // Push notifications
    Route::group(['prefix' => 'push'], function () {
        Route::get('token', [\App\Http\Controllers\PushNotificationController::class, 'token'])->name('push.token');
        Route::get('key', [\App\Http\Controllers\PushNotificationController::class, 'key'])->name('push.key');
        Route::post('register', [\App\Http\Controllers\PushNotificationController::class, 'register'])->name('push.register');
        Route::post('unregister', [\App\Http\Controllers\PushNotificationController::class, 'unregister'])->name('push.unregister');
    });

    // admin pages
    Route::group(['middleware' => ['can:admin']], function () {
        Route::get('settings/{tab?}/{section?}', 'SettingsController@index')->name('settings');
        Route::put('settings/{name}', 'SettingsController@update')->name('settings.update');
        Route::delete('settings/{name}', 'SettingsController@destroy')->name('settings.destroy');

        Route::post('alert/transports/{transport}/test', [\App\Http\Controllers\AlertTransportController::class, 'test'])->name('alert.transports.test');
    });

    Route::get('plugin/settings', 'PluginAdminController')->name('plugin.admin');
    Route::get('plugin/settings/{plugin:plugin_name}', 'PluginSettingsController')->name('plugin.settings');
    Route::post('plugin/settings/{plugin:plugin_name}', 'PluginSettingsController@update')->name('plugin.update');
    Route::get('plugin', 'PluginLegacyController@redirect');
    Route::redirect('plugin/view=admin', '/plugin/admin');
    Route::get('plugin/p={pluginName}', 'PluginLegacyController@redirect');
    Route::any('plugin/v1/{plugin:plugin_name}', 'PluginLegacyController')->name('plugin.legacy');
    Route::get('plugin/{plugin:plugin_name}', 'PluginPageController')->name('plugin.page');

    // old route redirects
    Route::permanentRedirect('poll-log', 'poller/log');

    // Two Factor Auth
    Route::group(['prefix' => '2fa', 'namespace' => 'Auth'], function () {
        Route::get('', 'TwoFactorController@showTwoFactorForm')->name('2fa.form');
        Route::post('', 'TwoFactorController@verifyTwoFactor')->name('2fa.verify');
        Route::post('add', 'TwoFactorController@create')->name('2fa.add');
        Route::post('cancel', 'TwoFactorController@cancelAdd')->name('2fa.cancel');
        Route::post('remove', 'TwoFactorController@destroy')->name('2fa.remove');

        Route::post('{user}/unlock', 'TwoFactorManagementController@unlock')->name('2fa.unlock');
        Route::delete('{user}', 'TwoFactorManagementController@destroy')->name('2fa.delete');
    });

    // Ajax routes
    Route::group(['prefix' => 'ajax'], function () {
        // page ajax controllers
        Route::resource('location', 'LocationController', ['only' => ['update', 'destroy']]);
        Route::resource('pollergroup', 'PollerGroupController', ['only' => ['destroy']]);
        // misc ajax controllers
        Route::group(['namespace' => 'Ajax'], function () {
            Route::post('set_map_group', 'AvailabilityMapController@setGroup');
            Route::post('set_map_view', 'AvailabilityMapController@setView');
            Route::post('set_resolution', 'ResolutionController@set');
            Route::get('netcmd', 'NetCommand@run');
            Route::post('ripe/raw', 'RipeNccApiController@raw');
            Route::get('snmp/capabilities', 'SnmpCapabilities')->name('snmp.capabilities');
        });

        Route::get('settings/list', 'SettingsController@listAll')->name('settings.list');

        // form ajax handlers, perhaps should just be page controllers
        Route::group(['prefix' => 'form', 'namespace' => 'Form'], function () {
            Route::resource('widget-settings', 'WidgetSettingsController');
            Route::post('copy-dashboard', 'CopyDashboardController@store');
        });

        // js select2 data controllers
        Route::group(['prefix' => 'select', 'namespace' => 'Select'], function () {
            Route::get('application', 'ApplicationController')->name('ajax.select.application');
            Route::get('bill', 'BillController')->name('ajax.select.bill');
            Route::get('dashboard', 'DashboardController')->name('ajax.select.dashboard');
            Route::get('device', 'DeviceController')->name('ajax.select.device');
            Route::get('device-field', 'DeviceFieldController')->name('ajax.select.device-field');
            Route::get('device-group', 'DeviceGroupController')->name('ajax.select.device-group');
            Route::get('port-group', 'PortGroupController')->name('ajax.select.port-group');
            Route::get('eventlog', 'EventlogController')->name('ajax.select.eventlog');
            Route::get('graph', 'GraphController')->name('ajax.select.graph');
            Route::get('graph-aggregate', 'GraphAggregateController')->name('ajax.select.graph-aggregate');
            Route::get('graylog-streams', 'GraylogStreamsController')->name('ajax.select.graylog-streams');
            Route::get('syslog', 'SyslogController')->name('ajax.select.syslog');
            Route::get('location', 'LocationController')->name('ajax.select.location');
            Route::get('munin', 'MuninPluginController')->name('ajax.select.munin');
            Route::get('service', 'ServiceController')->name('ajax.select.service');
            Route::get('template', 'ServiceTemplateController')->name('ajax.select.template');
            Route::get('poller-group', 'PollerGroupController')->name('ajax.select.poller-group');
            Route::get('port', 'PortController')->name('ajax.select.port');
            Route::get('port-field', 'PortFieldController')->name('ajax.select.port-field');
        });

        // jquery bootgrid data controllers
        Route::group(['prefix' => 'table', 'namespace' => 'Table'], function () {
            Route::post('alert-schedule', 'AlertScheduleController');
            Route::post('customers', 'CustomersController');
            Route::post('device', 'DeviceController');
            Route::post('edit-ports', 'EditPortsController');
            Route::post('eventlog', 'EventlogController');
            Route::post('fdb-tables', 'FdbTablesController');
            Route::post('graylog', 'GraylogController');
            Route::post('location', 'LocationController');
            Route::post('mempools', 'MempoolsController');
            Route::post('outages', 'OutagesController');
            Route::post('port-nac', 'PortNacController');
            Route::post('ports', 'PortsController')->name('table.ports');
            Route::post('routes', 'RoutesTablesController');
            Route::post('syslog', 'SyslogController');
            Route::post('vminfo', 'VminfoController');
        });

        // dashboard widgets
        Route::group(['prefix' => 'dash', 'namespace' => 'Widgets'], function () {
            Route::post('alerts', 'AlertsController');
            Route::post('alertlog', 'AlertlogController');
            Route::post('availability-map', 'AvailabilityMapController');
            Route::post('component-status', 'ComponentStatusController');
            Route::post('device-summary-horiz', 'DeviceSummaryHorizController');
            Route::post('device-summary-vert', 'DeviceSummaryVertController');
            Route::post('eventlog', 'EventlogController');
            Route::post('generic-graph', 'GraphController');
            Route::post('generic-image', 'ImageController');
            Route::post('globe', 'GlobeController');
            Route::post('graylog', 'GraylogController');
            Route::post('placeholder', 'PlaceholderController');
            Route::post('notes', 'NotesController');
            Route::post('server-stats', 'ServerStatsController');
            Route::post('syslog', 'SyslogController');
            Route::post('top-devices', 'TopDevicesController');
            Route::post('top-interfaces', 'TopInterfacesController');
            Route::post('top-errors', 'TopErrorsController');
            Route::post('worldmap', 'WorldMapController');
            Route::post('alertlog-stats', 'AlertlogStatsController');
        });
    });

    // demo helper
    Route::permanentRedirect('demo', '/');
});

// installation routes
Route::group(['prefix' => 'install', 'namespace' => 'Install'], function () {
    Route::get('/', 'InstallationController@redirectToFirst')->name('install');
    Route::get('/checks', 'ChecksController@index')->name('install.checks');
    Route::get('/database', 'DatabaseController@index')->name('install.database');
    Route::get('/user', 'MakeUserController@index')->name('install.user');
    Route::get('/finish', 'FinalizeController@index')->name('install.finish');

    Route::post('/user/create', 'MakeUserController@create')->name('install.action.user');
    Route::post('/database/test', 'DatabaseController@test')->name('install.acton.test-database');
    Route::get('/ajax/database/migrate', 'DatabaseController@migrate')->name('install.action.migrate');
    Route::get('/ajax/steps', 'InstallationController@stepsCompleted')->name('install.action.steps');
    Route::any('{path?}', 'InstallationController@invalid')->where('path', '.*'); // 404
});

// Legacy routes
Route::any('/dummy_legacy_auth/{path?}', 'LegacyController@dummy')->middleware('auth');
Route::any('/dummy_legacy_unauth/{path?}', 'LegacyController@dummy');
Route::any('/{path?}', 'LegacyController@index')
    ->where('path', '^((?!_debugbar).)*')
    ->middleware('auth');
