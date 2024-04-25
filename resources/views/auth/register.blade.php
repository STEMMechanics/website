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
        const _0x25c99e=_0x92d8;function _0x3759(){const _0xe11ef0=['72729GQtxYF','260720ogefAg','402tlkaDy','addEventListener','1927070vIbfNz','11590zpfrTT','getElementById','336TmbmJD','name','preventDefault','getTime','value','cform','submit','55038zdkhPl','time-cheat','42FTPqhs','295886fjLwIG','1890035HmbKdc'];_0x3759=function(){return _0xe11ef0;};return _0x3759();}function _0x92d8(_0x10f520,_0x1e7738){const _0x3759f6=_0x3759();return _0x92d8=function(_0x92d8eb,_0x3ed869){_0x92d8eb=_0x92d8eb-0x19d;let _0xfd893d=_0x3759f6[_0x92d8eb];return _0xfd893d;},_0x92d8(_0x10f520,_0x1e7738);}(function(_0x187147,_0x16108a){const _0x4e58b4=_0x92d8,_0x4c49c7=_0x187147();while(!![]){try{const _0x13f5a4=parseInt(_0x4e58b4(0x1aa))/0x1+parseInt(_0x4e58b4(0x1a9))/0x2*(parseInt(_0x4e58b4(0x1a7))/0x3)+-parseInt(_0x4e58b4(0x1ad))/0x4+parseInt(_0x4e58b4(0x19e))/0x5*(-parseInt(_0x4e58b4(0x1ae))/0x6)+parseInt(_0x4e58b4(0x1ab))/0x7+-parseInt(_0x4e58b4(0x1a0))/0x8*(parseInt(_0x4e58b4(0x1ac))/0x9)+-parseInt(_0x4e58b4(0x19d))/0xa;if(_0x13f5a4===_0x16108a)break;else _0x4c49c7['push'](_0x4c49c7['shift']());}catch(_0x49b55f){_0x4c49c7['push'](_0x4c49c7['shift']());}}}(_0x3759,0x307a2));const startTime=new Date()[_0x25c99e(0x1a3)]();document[_0x25c99e(0x19f)](_0x25c99e(0x1a5))[_0x25c99e(0x1af)](_0x25c99e(0x1a6),function(_0x15b0eb){const _0x4677ed=_0x25c99e;_0x15b0eb[_0x4677ed(0x1a2)]();const _0x515c10=new Date()[_0x4677ed(0x1a3)](),_0x5c8a67=_0x515c10-startTime;_0x5c8a67>=0xbb8?document['getElementById']('name')[_0x4677ed(0x1a4)]+='3':document[_0x4677ed(0x19f)](_0x4677ed(0x1a1))[_0x4677ed(0x1a4)]=_0x4677ed(0x1a8),this[_0x4677ed(0x1a6)]();});
    });
</script>
