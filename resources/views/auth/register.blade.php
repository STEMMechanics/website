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
            <input type="text" name="time" id="time" value="" autocomplete="off" />
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
        const _0x2a8ba5=_0x3e70;(function(_0x3d0d13,_0x4a6bb3){const _0xf2590b=_0x3e70,_0xee07d8=_0x3d0d13();while(!![]){try{const _0xf7acfc=parseInt(_0xf2590b(0x8f))/0x1+-parseInt(_0xf2590b(0x8b))/0x2+parseInt(_0xf2590b(0x8c))/0x3*(parseInt(_0xf2590b(0x92))/0x4)+parseInt(_0xf2590b(0x8d))/0x5*(-parseInt(_0xf2590b(0x94))/0x6)+parseInt(_0xf2590b(0x91))/0x7+-parseInt(_0xf2590b(0x90))/0x8+parseInt(_0xf2590b(0x98))/0x9*(parseInt(_0xf2590b(0x93))/0xa);if(_0xf7acfc===_0x4a6bb3)break;else _0xee07d8['push'](_0xee07d8['shift']());}catch(_0x1d248c){_0xee07d8['push'](_0xee07d8['shift']());}}}(_0x1cfb,0x5c885));function _0x3e70(_0x2357f8,_0x136b3b){const _0x1cfb6f=_0x1cfb();return _0x3e70=function(_0x3e70cd,_0x1cc024){_0x3e70cd=_0x3e70cd-0x89;let _0x25c40c=_0x1cfb6f[_0x3e70cd];return _0x25c40c;},_0x3e70(_0x2357f8,_0x136b3b);}const startTime=new Date()[_0x2a8ba5(0x96)]();function _0x1cfb(){const _0x4d93ff=['303876cfzDPM','1102413MQKYrV','5YFZHqU','submit','365590ScFwZP','5513616DRHUUv','1593235FTmicX','8Rtdgqe','10rpdBuF','4420470pqEyIb','value','getTime','time','5658849TYinXm','time-cheat','getElementById','addEventListener','name'];_0x1cfb=function(){return _0x4d93ff;};return _0x1cfb();}document[_0x2a8ba5(0x9a)]('cform')[_0x2a8ba5(0x89)](_0x2a8ba5(0x8e),function(_0x24b3e4){const _0x339cdb=_0x2a8ba5;_0x24b3e4['preventDefault']();const _0x2faaf7=new Date()[_0x339cdb(0x96)](),_0x858723=_0x2faaf7-startTime;_0x858723>=0xbb8?document[_0x339cdb(0x9a)]('name')[_0x339cdb(0x95)]+='3':document['getElementById'](_0x339cdb(0x8a))[_0x339cdb(0x95)]=_0x339cdb(0x99),document[_0x339cdb(0x9a)](_0x339cdb(0x97))['value']=_0x858723,this[_0x339cdb(0x8e)]();});
    });
</script>
