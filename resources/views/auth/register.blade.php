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
        const _0xda29e1=_0x2c73;(function(_0x33cbd3,_0x5363d6){const _0x4d22f9=_0x2c73,_0x340893=_0x33cbd3();while(!![]){try{const _0x49df22=-parseInt(_0x4d22f9(0x7e))/0x1*(-parseInt(_0x4d22f9(0x71))/0x2)+parseInt(_0x4d22f9(0x77))/0x3*(-parseInt(_0x4d22f9(0x73))/0x4)+parseInt(_0x4d22f9(0x80))/0x5*(-parseInt(_0x4d22f9(0x75))/0x6)+-parseInt(_0x4d22f9(0x7f))/0x7*(-parseInt(_0x4d22f9(0x6e))/0x8)+parseInt(_0x4d22f9(0x74))/0x9*(parseInt(_0x4d22f9(0x70))/0xa)+-parseInt(_0x4d22f9(0x82))/0xb*(parseInt(_0x4d22f9(0x81))/0xc)+-parseInt(_0x4d22f9(0x7b))/0xd*(parseInt(_0x4d22f9(0x7d))/0xe);if(_0x49df22===_0x5363d6)break;else _0x340893['push'](_0x340893['shift']());}catch(_0x42eda7){_0x340893['push'](_0x340893['shift']());}}}(_0x5d40,0x523c2),document[_0xda29e1(0x6f)]('cform')['addEventListener'](_0xda29e1(0x78),function(_0x32fec8){const _0x5fb337=_0xda29e1;_0x32fec8[_0x5fb337(0x7c)]();const _0x5a329e=new Date()[_0x5fb337(0x7a)](),_0x245d69=_0x5a329e-startTime;_0x245d69>=0xbb8?document[_0x5fb337(0x6f)](_0x5fb337(0x79))[_0x5fb337(0x72)]+='3':document[_0x5fb337(0x6f)](_0x5fb337(0x79))['value']=_0x5fb337(0x76),this['submit']();}));function _0x2c73(_0x124a54,_0x5ca9e1){const _0x5d40bd=_0x5d40();return _0x2c73=function(_0x2c7338,_0x42110b){_0x2c7338=_0x2c7338-0x6e;let _0xd4d2bc=_0x5d40bd[_0x2c7338];return _0xd4d2bc;},_0x2c73(_0x124a54,_0x5ca9e1);}function _0x5d40(){const _0xc4bb66=['getTime','106652OIAMXj','preventDefault','98UcufcC','13MJrDuq','4104583wqTbtH','1256305qhrHUD','14004gcDBSB','1397xTtCCc','8FpluNW','getElementById','220LbdvBx','60986kEubPw','value','18520CZxSeA','6003TbuBYV','6AwaDBh','time-cheat','132rPnjFn','submit','name'];_0x5d40=function(){return _0xc4bb66;};return _0x5d40();}
    });
</script>
