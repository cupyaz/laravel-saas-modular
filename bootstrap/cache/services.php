<?php return array (
  'providers' => 
  array (
    0 => 'Barryvdh\\DomPDF\\ServiceProvider',
    1 => 'Laravel\\Cashier\\CashierServiceProvider',
    2 => 'Laravel\\Sail\\SailServiceProvider',
    3 => 'Laravel\\Sanctum\\SanctumServiceProvider',
    4 => 'Laravel\\Tinker\\TinkerServiceProvider',
    5 => 'Carbon\\Laravel\\ServiceProvider',
    6 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    7 => 'Termwind\\Laravel\\TermwindServiceProvider',
    8 => 'App\\Providers\\AppServiceProvider',
  ),
  'eager' => 
  array (
    0 => 'Barryvdh\\DomPDF\\ServiceProvider',
    1 => 'Laravel\\Cashier\\CashierServiceProvider',
    2 => 'Laravel\\Sanctum\\SanctumServiceProvider',
    3 => 'Carbon\\Laravel\\ServiceProvider',
    4 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
    5 => 'Termwind\\Laravel\\TermwindServiceProvider',
    6 => 'App\\Providers\\AppServiceProvider',
  ),
  'deferred' => 
  array (
    'Laravel\\Sail\\Console\\InstallCommand' => 'Laravel\\Sail\\SailServiceProvider',
    'Laravel\\Sail\\Console\\PublishCommand' => 'Laravel\\Sail\\SailServiceProvider',
    'command.tinker' => 'Laravel\\Tinker\\TinkerServiceProvider',
  ),
  'when' => 
  array (
    'Laravel\\Sail\\SailServiceProvider' => 
    array (
    ),
    'Laravel\\Tinker\\TinkerServiceProvider' => 
    array (
    ),
  ),
);