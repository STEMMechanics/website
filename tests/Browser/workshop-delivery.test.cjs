const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function setup(fetch) {
    const context={window:{},fetch,AbortController,FormData:class {constructor(form){this.rows=form.rows || [];} set(){} [Symbol.iterator](){return this.rows[Symbol.iterator]();}},setTimeout,clearTimeout};
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-delivery.js','utf8'),context);
    const state=context.window.SM.workshopDelivery({summary:{total:32,shipping:0,shipping_method_code:'pickup'},ticketAmount:132});
    state.$el={action:{},getAttribute:name=>name==='action'?'/delivery':null};
    return state;
}
test('delivery quotes update confirmed equipment costs while keeping ticket amount separate',async()=>{
    const state=setup(async(url)=>{assert.equal(url,'/delivery');return {ok:true,json:async()=>({summary:{total:42,shipping:10,shipping_method_code:'post'}})};} );
    state.revision=1;state.loading=true;
    await state.refreshQuote(1);
    assert.equal(state.quote.total,42);assert.equal(state.method,'post');assert.equal(state.ticketAmount,132);assert.equal(state.loading,false);
});
test('stale quotes cannot replace newer totals and errors prevent continuation',async()=>{
    let resolve;
    const state=setup(()=>new Promise(r=>resolve=r));
    state.revision=1;
    const request=state.refreshQuote(1);
    state.revision=2;state.quote={total:50};
    resolve({ok:true,json:async()=>({summary:{total:42}})});
    await request;
    assert.equal(state.quote.total,50);
    const invalid=setup(async()=>({ok:false,json:async()=>({errors:{billing_postcode:['Invalid postcode']}})}));
    invalid.revision=1;await invalid.refreshQuote(1);
    assert.equal(invalid.quoteError,'Invalid postcode');assert.equal(invalid.quote.total,32);
});

test('unchanged delivery selections and refreshed confirmed totals do not interrupt Continue',()=>{
    const state=setup(()=>{});
    state.$el.rows=[['shipping_method_code','pickup'],['confirmed_total','32']];
    state.scheduleQuote();
    clearTimeout(state.timer);
    assert.equal(state.loading,true);
    state.loading=false;
    state.$el.rows=[['shipping_method_code','pickup'],['confirmed_total','42']];
    state.scheduleQuote();
    assert.equal(state.loading,false);
    assert.equal(state.revision,1);
    state.destroy();
});
