Instruccion de como se esta creando:



(kitloong para generar las migraciones a apartir de la base de datos)

---

* Instalacion de Sanctum (sistema de token para autenticar usuarios):

composer require laravel/sanctum

* Instalar paquetes necesarios, y generar la tabla de tokens de sanctum

php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"



* Cargar la nueva tabla a la base de datos php artisan migrate







 'providers' => \[

        'users' => \[

            'driver' => 'eloquent',

            'model' => App\\Models\\Usuario::class,

        ],

