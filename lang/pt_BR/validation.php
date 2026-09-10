<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Only the rules actually triggered by the Login/Register forms
    | (required, string, email, max/min:string, unique, confirmed) are
    | translated here — not a full copy of Laravel's validation.php.
    |
    */

    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'unique' => 'O :attribute informado já está em uso.',
    'confirmed' => 'A confirmação de :attribute não confere.',

    'max' => [
        'string' => 'O campo :attribute não pode ter mais que :max caracteres.',
    ],

    'min' => [
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
    ],

];
