@component('mail::message')


***VCDN*** Portal


**Password Change Confirmation !!**

We have received a request to change the password for the user account **{{$name}}**.

***Verification code:   <u>{{$validation_code}}</u>***

To change your password, please click on the link below, enter the verification code
and fill in the fields to generate a new password.

<a href="{{ $link }}">Change User Password</a>



If you did not make the request, please disregard this email.



Thank you,

    VCDN Portal

@endcomponent
