@component('mail::message')


Welcome {{$name}}, to the ***VCDN*** Portal


You can access the user name and password provided
in the link below.

**<a href="{{ $link }}">{{ $link}}</a>**,


> Username : **{{ $user_name }}**

> Password : **{{ $password }}**


Thank you,

> VCDN Portal

@endcomponent
