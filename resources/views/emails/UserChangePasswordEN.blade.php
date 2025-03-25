@component('mail::message')


Welcome {{$name}}, to the ***VCDN*** Portal


**Your password has been changed successfully !!**

You can access it from the link below, entering
the username and the new password provided.

**<a href="{{ $link }}">{{ $link}}</a>**,


> Username : **{{ $user_name }}**

> Password : **{{ $password }}**



Thank you,

    VCDN Portal
@endcomponent
