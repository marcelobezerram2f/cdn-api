@component('mail::message')


Portal  ***VCDN***


**Confirmación de alteración de contraseña !!**

Recibimos una solicitud para alterar la contraseña de la cuenta del usuário **{{$name}}**.

***Código verificador:   <u>{{$validation_code}}</u>***

Para cambiar su contraseña, haga clic en el enlace a continuación, ingrese el código verificador
y complete los campos para generar una nueva contraseña.

<a href="{{ $link }}">Alterar contraseña del usuário</a>



Si no fue usted quien solicitó, desconsidere éste email.



Muchas gracias,

    Portal VCDN

@endcomponent
