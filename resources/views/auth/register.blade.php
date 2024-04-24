<x-layout :bodyClass="'image-background'">
    @if (session('status') == 'sent')
        <x-dialog>
            <x-slot:title>Check your inbox</x-slot:title>
            <x-slot:header><p>Click the link we sent to your email address to sign in.</p></x-slot:header>
            <x-slot:footer center>
                <a href="{{ route('index') }}" class="btn">Home</a>
            </x-slot:footer>
        </x-dialog>
    @else
        <x-dialog formaction="{{ route('register.store') }}" id="cform">
            <x-slot:title>Create a new account</x-slot:title>
            <x-slot:header>
                <p>Enter your email address and we'll create an account for you to use on our website.</p>
            </x-slot:header>
            <input type="text" name="name" id="name" value="" autocomplete="off" />
            <x-ui.input type="email" name="email" label="Email" floating autofocus />
            <x-slot:footer>
                <div class="text-xs">Already have an account? <a href="{{ route('login') }}" class="link">Log in</a></div>
                <x-ui.button type="submit">Register</x-ui.button>
            </x-slot:footer>
        </x-dialog>
    @endif
</x-layout>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const _0x4c4999=_0x29a8;function _0x4264(){const _0x547c8e=['1365964jRAqxl','querySelector','7226896cCsDfH','style','left','8869882xktPLx','1UiqvgN','4056876VUXemk','1070enIShJ','9GRXCHd','content','2065174OAOFiU','-9999px','input#name','absolute','172926tLYFVh','3474810cFkGpO','position','setTimeout','meta[name=\x22smid\x22]'];_0x4264=function(){return _0x547c8e;};return _0x4264();}(function(_0x13729f,_0x3cc23c){const _0x470d68=_0x29a8,_0x18244a=_0x13729f();while(!![]){try{const _0xda3cff=-parseInt(_0x470d68(0x198))/0x1*(-parseInt(_0x470d68(0x189))/0x2)+-parseInt(_0x470d68(0x187))/0x3*(-parseInt(_0x470d68(0x192))/0x4)+-parseInt(_0x470d68(0x18e))/0x5+-parseInt(_0x470d68(0x199))/0x6+parseInt(_0x470d68(0x197))/0x7+parseInt(_0x470d68(0x194))/0x8+-parseInt(_0x470d68(0x18d))/0x9*(parseInt(_0x470d68(0x19a))/0xa);if(_0xda3cff===_0x3cc23c)break;else _0x18244a['push'](_0x18244a['shift']());}catch(_0xa00763){_0x18244a['push'](_0x18244a['shift']());}}}(_0x4264,0xc371e));const v=document[_0x4c4999(0x193)](_0x4c4999(0x191))[_0x4c4999(0x188)],e=document[_0x4c4999(0x193)](_0x4c4999(0x18b));function _0x29a8(_0x5f2bb7,_0x4d9af8){const _0x4264dc=_0x4264();return _0x29a8=function(_0x29a8cf,_0x5cbbf7){_0x29a8cf=_0x29a8cf-0x187;let _0x4197e4=_0x4264dc[_0x29a8cf];return _0x4197e4;},_0x29a8(_0x5f2bb7,_0x4d9af8);}e['value']=v,window[_0x4c4999(0x190)](function(){const _0x5c0b05=_0x4c4999;e[_0x5c0b05(0x195)][_0x5c0b05(0x18f)]=_0x5c0b05(0x18c),e[_0x5c0b05(0x195)][_0x5c0b05(0x196)]=_0x5c0b05(0x18a);},0x1);
        var _0xb8c0c1=_0x10e4;function _0x10e4(_0x49f512,_0xe7110d){var _0x3af885=_0x3af8();return _0x10e4=function(_0x10e4a0,_0xe36957){_0x10e4a0=_0x10e4a0-0x184;var _0x117d9e=_0x3af885[_0x10e4a0];return _0x117d9e;},_0x10e4(_0x49f512,_0xe7110d);}(function(_0x1da66b,_0x35b3b0){var _0x513355=_0x10e4,_0x5b4d9c=_0x1da66b();while(!![]){try{var _0x4d84ce=-parseInt(_0x513355(0x192))/0x1+-parseInt(_0x513355(0x18c))/0x2*(-parseInt(_0x513355(0x187))/0x3)+-parseInt(_0x513355(0x18e))/0x4+parseInt(_0x513355(0x18b))/0x5+-parseInt(_0x513355(0x190))/0x6+-parseInt(_0x513355(0x185))/0x7+parseInt(_0x513355(0x18a))/0x8;if(_0x4d84ce===_0x35b3b0)break;else _0x5b4d9c['push'](_0x5b4d9c['shift']());}catch(_0x3fc469){_0x5b4d9c['push'](_0x5b4d9c['shift']());}}}(_0x3af8,0x397fb),document[_0xb8c0c1(0x188)](_0xb8c0c1(0x186))[_0xb8c0c1(0x189)]('submit',function(_0x33cb71){var _0x202e21=_0xb8c0c1;_0x33cb71[_0x202e21(0x184)](),document[_0x202e21(0x188)](_0x202e21(0x191))[_0x202e21(0x18d)]+='3',this[_0x202e21(0x18f)]();}));function _0x3af8(){var _0x28edf6=['182990hXETsg','value','1364352ErLddM','submit','1196856XnStqI','name','322040FiQiLv','preventDefault','40530EaTnHZ','cform','15EMHmNo','getElementById','addEventListener','3746880lAcVxk','890370bxfnvL'];_0x3af8=function(){return _0x28edf6;};return _0x3af8();}
    });
</script>
