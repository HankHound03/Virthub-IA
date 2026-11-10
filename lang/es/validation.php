<?php

/*
 * Mensajes de validación en español.
 *
 * Laravel traía los mensajes en inglés y por eso aparecía
 * "The new password field confirmation does not match." en medio de una
 * aplicación en español.
 *
 * Las frases están escritas para que funcionen con cualquier campo, sin depender
 * del género: "Falta la contraseña" en vez de "la contraseña es obligatorio".
 */
return [
    'accepted' => 'Debes aceptar :attribute.',
    'active_url' => ':attribute no es una dirección válida.',
    'after' => ':attribute debe ser una fecha posterior a :date.',
    'alpha' => ':attribute solo puede contener letras.',
    'alpha_dash' => ':attribute solo puede contener letras, números y guiones.',
    'alpha_num' => ':attribute solo puede contener letras y números.',
    'array' => ':attribute debe ser una lista.',
    'before' => ':attribute debe ser una fecha anterior a :date.',

    'between' => [
        'numeric' => ':attribute debe estar entre :min y :max.',
        'file' => ':attribute debe pesar entre :min y :max kilobytes.',
        'string' => ':attribute debe tener entre :min y :max caracteres.',
        'array' => ':attribute debe tener entre :min y :max elementos.',
    ],

    'boolean' => ':attribute solo puede ser verdadero o falso.',
    'confirmed' => ':attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'date' => ':attribute no es una fecha válida.',
    'date_format' => ':attribute no tiene el formato :format.',

    'different' => ':attribute y :other deben ser distintos.',
    'digits' => ':attribute debe tener :digits dígitos.',
    'digits_between' => ':attribute debe tener entre :min y :max dígitos.',
    'email' => ':attribute no es una dirección de correo válida.',
    'exists' => ':attribute seleccionado no existe.',

    'file' => ':attribute tiene que ser un archivo.',
    'filled' => 'Falta :attribute.',

    'image' => ':attribute tiene que ser una imagen.',
    'in' => ':attribute seleccionado no es válido.',
    'integer' => ':attribute debe ser un número entero.',

    'max' => [
        'numeric' => ':attribute no puede ser mayor que :max.',
        'file' => ':attribute no puede pesar más de :max kilobytes.',
        'string' => ':attribute no puede tener más de :max caracteres.',
        'array' => ':attribute no puede tener más de :max elementos.',
    ],

    'mimes' => ':attribute tiene que ser un archivo de tipo: :values.',
    'mimetypes' => ':attribute tiene que ser un archivo de tipo: :values.',

    'min' => [
        'numeric' => ':attribute debe ser al menos :min.',
        'file' => ':attribute debe pesar al menos :min kilobytes.',
        'string' => ':attribute debe tener al menos :min caracteres.',
        'array' => ':attribute debe tener al menos :min elementos.',
    ],

    'not_in' => ':attribute seleccionado no es válido.',
    'numeric' => ':attribute debe ser un número.',
    'present' => 'Falta :attribute.',

    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'Falta :attribute.',
    'required_if' => 'Falta :attribute cuando :other es :value.',
    'required_with' => 'Falta :attribute cuando :values está presente.',
    'required_without' => 'Falta :attribute cuando :values no está presente.',
    'same' => ':attribute y :other deben coincidir.',

    'size' => [
        'numeric' => ':attribute debe ser :size.',
        'file' => ':attribute debe pesar :size kilobytes.',
        'string' => ':attribute debe tener :size caracteres.',
        'array' => ':attribute debe contener :size elementos.',
    ],

    'string' => ':attribute debe ser texto.',
    'timezone' => ':attribute tiene que ser una zona horaria válida.',
    'unique' => ':attribute ya está en uso.',
    'uploaded' => 'No se pudo subir :attribute.',
    'url' => ':attribute no es una dirección válida.',

    /*
    |--------------------------------------------------------------------------
    | Nombres legibles de los campos
    |--------------------------------------------------------------------------
    | Sin esto, un mensaje dice "password_confirmation" en vez de
    | "la confirmación de la contraseña".
    */
    'attributes' => [
        'name' => 'el nombre',
        'username' => 'el nombre de usuario',
        'email' => 'el correo',
        'password' => 'la contraseña',
        'password_confirmation' => 'la confirmación de la contraseña',
        'current_password' => 'la contraseña actual',
        'new_password' => 'la contraseña nueva',
        'new_password_confirmation' => 'la confirmación de la contraseña nueva',
        'content' => 'el contenido',
        'reason' => 'el motivo',
        'title' => 'el título',
        'message' => 'el mensaje',
        'code' => 'el código',
        'recovery_code' => 'el código de recuperación',
        'profile_image' => 'la foto de perfil',
        'profile_frame_color' => 'el color del marco',
        'role' => 'el rol',
        'admin_username' => 'el nombre de usuario del administrador',
        'admin_password' => 'la contraseña del administrador',
        'admin_password_confirmation' => 'la confirmación de la contraseña del administrador',
    ],
];
