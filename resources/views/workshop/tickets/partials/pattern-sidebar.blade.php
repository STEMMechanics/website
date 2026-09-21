<div aria-hidden="true" @class([
    'hidden w-64 shrink-0 -m-5 ml-0 rounded-tr-lg rounded-br-lg bg-gray-50',
    'md:block' => !($wide ?? false),
    'lg:block' => $wide ?? false,
]) style="background-image:url('{{ asset('stem-pattern.svg') }}')"></div>
