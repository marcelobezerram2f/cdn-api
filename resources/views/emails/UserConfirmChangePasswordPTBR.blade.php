@component('mail::message')


Portal  ***VCDN***


**Confirmação de alteração de senha !!**

Recebemos uma solicitação para alterar a senha da conta do usuário **{{$name}}**.


***Código verificador:   <u>{{$validation_code}}</u>***


Para alterar a senha, por favor clique no link abaixo, informe o código verificador
e preencha os campos para gerar uma nova senha.

<a href="{{ $link }}">Alterar senha do usuário</a>



Se não foi você quem solicitou, desconsidere éste email.



Obrigado,

    Portal VCDN

@endcomponent
