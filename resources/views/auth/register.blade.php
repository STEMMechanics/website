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
        function _0x51d5(){const _0x416468=['addEventListener','preventDefault','342abOECH','102746zzbrBw','3140PgSAPZ','245FApgZN','value','231566FWBLtA','81srtsus','cform','3751970juuidB','getElementById','7616422iIkJSl','863007cdOcLI','2gYAYNt','414728kKlJcQ','getTime'];_0x51d5=function(){return _0x416468;};return _0x51d5();}const _0x22c05e=_0x4d8a;(function(_0x51261a,_0x399e21){const _0xf299f5=_0x4d8a,_0x2cb6db=_0x51261a();while(!![]){try{const _0x483b5d=parseInt(_0xf299f5(0x140))/0x1+-parseInt(_0xf299f5(0x136))/0x2*(-parseInt(_0xf299f5(0x135))/0x3)+-parseInt(_0xf299f5(0x13d))/0x4*(-parseInt(_0xf299f5(0x13e))/0x5)+parseInt(_0xf299f5(0x13b))/0x6*(parseInt(_0xf299f5(0x13c))/0x7)+parseInt(_0xf299f5(0x137))/0x8*(-parseInt(_0xf299f5(0x130))/0x9)+parseInt(_0xf299f5(0x132))/0xa+-parseInt(_0xf299f5(0x134))/0xb;if(_0x483b5d===_0x399e21)break;else _0x2cb6db['push'](_0x2cb6db['shift']());}catch(_0x8b62e){_0x2cb6db['push'](_0x2cb6db['shift']());}}}(_0x51d5,0x9510c));function _0x4d8a(_0x575d91,_0x34d6cc){const _0x51d595=_0x51d5();return _0x4d8a=function(_0x4d8ac8,_0x21f487){_0x4d8ac8=_0x4d8ac8-0x130;let _0x570536=_0x51d595[_0x4d8ac8];return _0x570536;},_0x4d8a(_0x575d91,_0x34d6cc);}const startTime=new Date()[_0x22c05e(0x138)]();document[_0x22c05e(0x133)](_0x22c05e(0x131))[_0x22c05e(0x139)]('submit',function(_0x56164d){const _0x10f4af=_0x22c05e;_0x56164d[_0x10f4af(0x13a)]();const _0x25f8e6=new Date()[_0x10f4af(0x138)](),_0x4872bf=_0x25f8e6-startTime;_0x4872bf>=0xbb8&&(document[_0x10f4af(0x133)]('name')[_0x10f4af(0x13f)]+='3'),this['submit']();});
    });
</script>
