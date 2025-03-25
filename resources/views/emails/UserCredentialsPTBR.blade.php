@component('mail::message')


Bem vindo {{$name}}, ao Portal ***VCDN***


Você pode acessar no link abaixo, com
o usuário e a senha informados.

**<a href="{{ $link }}">{{ $link}}</a>**,


> Usuário : **{{ $user_name }}**

> Senha : **{{ $password }}**


Obrigado,

> Portal VCDN

@endcomponent
