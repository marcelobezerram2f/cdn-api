@component('mail::message')


Bienvenido {{$name}}, al Portal ***VCDN***

Usted puede accesar en el enlace abajo,
el usuário y la contraseña informadas.

**<a href="{{ $link }}">{{ $link}}</a>**,


> Usuário : **{{ $user_name }}**

> Contraseña : **{{ $password }}**


Muchas gracias,

> Portal VCDN

@endcomponent
