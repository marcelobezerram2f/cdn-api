@component('mail::message')


Bienvenido {{$name}}, al Portal  ***VCDN***


**Su constraseña fue alterada con éxito !!**

Usted puede accesar desde el enlace abajo, ingresando
el usuário y la nueva contraseña informada.

**<a href="{{ $link }}">{{ $link}}</a>**

> Usuário : **{{ $user_name }}**

> Contraseña : **{{ $password }}**



Muchas gracias,

    Portal VCDN
@endcomponent
