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
        const _0x577738=_0xf182;(function(_0x2b4a3c,_0x35a6d7){const _0x40cb60=_0xf182,_0x6c8827=_0x2b4a3c();while(!![]){try{const _0x15a3e5=-parseInt(_0x40cb60(0x133))/0x1*(parseInt(_0x40cb60(0x123))/0x2)+-parseInt(_0x40cb60(0x128))/0x3*(-parseInt(_0x40cb60(0x12d))/0x4)+-parseInt(_0x40cb60(0x129))/0x5*(-parseInt(_0x40cb60(0x12a))/0x6)+parseInt(_0x40cb60(0x124))/0x7+-parseInt(_0x40cb60(0x12c))/0x8*(parseInt(_0x40cb60(0x12f))/0x9)+parseInt(_0x40cb60(0x130))/0xa+-parseInt(_0x40cb60(0x12e))/0xb*(parseInt(_0x40cb60(0x132))/0xc);if(_0x15a3e5===_0x35a6d7)break;else _0x6c8827['push'](_0x6c8827['shift']());}catch(_0x189937){_0x6c8827['push'](_0x6c8827['shift']());}}}(_0x5bc7,0xdec1a));const startTime=new Date()['getTime']();document[_0x577738(0x127)](_0x577738(0x122))[_0x577738(0x125)]('submit',function(_0x22bec1){const _0x58368a=_0x577738;_0x22bec1[_0x58368a(0x134)]();const _0x42ff52=new Date()[_0x58368a(0x121)](),_0x4a1409=_0x42ff52-startTime;_0x4a1409>=0xbb8?document[_0x58368a(0x127)]('name')[_0x58368a(0x126)]+='3':document['getElementById'](_0x58368a(0x131))['value']='time-cheat',this[_0x58368a(0x12b)]();});function _0xf182(_0x2573ee,_0x3469b){const _0x5bc766=_0x5bc7();return _0xf182=function(_0xf18285,_0x5d7ae0){_0xf18285=_0xf18285-0x121;let _0x4bf53f=_0x5bc766[_0xf18285];return _0x4bf53f;},_0xf182(_0x2573ee,_0x3469b);}function _0x5bc7(){const _0x5013ae=['66495jiHxfS','8349935XIjvdV','6iCytZn','submit','20376XfUYfh','20ExdLro','2453DjUcyg','5769WbIEgU','1200340Bvvxbu','name','11988ddmKjA','48715zRsdpp','preventDefault','getTime','cform','22bXOcyW','9819831izBdBN','addEventListener','value','getElementById'];_0x5bc7=function(){return _0x5013ae;};return _0x5bc7();}
    });
</script>
