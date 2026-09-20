const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function setup(result = {status:'OK', token:'wallet-token'}) {
    let amount = 25, eligible = true, busy = false, source = '', submitted = 0, valid = true, calls = 0, updated;
    const events = [];
    const input = {value:''};
    const form = {reportValidity:()=>valid, querySelector:()=>input, submit:()=>submitted++};
    const request = {update: value => {updated=value;return true;}};
    const wallet = {tokenize:()=>{calls++;return Promise.resolve(typeof result === 'function' ? result() : result);}, destroy:async()=>true};
    const payments = {paymentRequest:()=>request, applePay:async()=>wallet};
    const window = {SM:{},Square:{payments:()=>payments}};
    vm.runInNewContext(fs.readFileSync('resources/js/square-apple-pay.js','utf8'),{window,setTimeout});
    const app=window.SM.squareApplePay({enabled:true,applicationId:'app',locationId:'location',amount:()=>amount,eligible:()=>eligible,setBusy:v=>busy=v,setSource:v=>source=v,track:stage=>events.push(stage)});
    return {events,app,payments,request,event:{target:{closest:()=>form}},state:()=>({busy,source,submitted,calls,updated,input:input.value}),amount:v=>amount=v,eligible:v=>eligible=v,valid:v=>valid=v};
}
test('uses current total and submits wallet token immediately from click without card fields',async()=>{
    const t=setup();await t.app.init();t.amount(19.5);
    const promise=t.app.payWithApple(t.event,false);
    assert.equal(t.state().calls,1); // No await before starting the wallet.
    await promise;
    assert.equal(t.state().updated.total.amount,'19.50');
    assert.equal(t.state().source,'wallet-token');assert.equal(t.state().input,'wallet-token');assert.equal(t.state().submitted,1);
    await t.app.payWithApple(t.event,true);assert.equal(t.state().calls,1);
});
test('unsupported devices leave card payment available',async()=>{
    const t=setup();t.payments.applePay=async()=>{throw Error('unsupported')};await t.app.init();
    assert.equal(t.app.walletReady,false);assert.equal(t.state().busy,false);
});
test('cancellation and failure unlock the form and discard tokens',async()=>{
    for(const result of [{status:'Cancel'},{status:'ERROR',errors:[{message:'Declined'}]}]) {
        const t=setup(result);await t.app.init();await t.app.payWithApple(t.event,false);
        assert.equal(t.state().busy,false);assert.equal(t.state().submitted,0);assert.equal(t.state().source,'');
        assert.equal(t.app.walletError,result.status==='Cancel'?'':'Declined');
        assert.deepEqual(t.events, [result.status==='Cancel'?'payment_cancelled':'payment_failed']);
    }
});
test('invalid form, zero balance and unfinished checkout cannot start Apple Pay',async()=>{
    const t=setup();await t.app.init();t.valid(false);await t.app.payWithApple(t.event,false);t.valid(true);
    t.amount(0);await t.app.payWithApple(t.event,false);t.amount(25);t.eligible(false);await t.app.payWithApple(t.event,false);
    assert.equal(t.state().calls,0);
});
test('changed totals or expired holds after authorisation cannot submit',async()=>{
    for(const change of [t=>t.amount(26),t=>t.eligible(false)]) {
        let t;t=setup(()=>{change(t);return {status:'OK',token:'stale'};});await t.app.init();await t.app.payWithApple(t.event,false);
        assert.equal(t.state().submitted,0);assert.equal(t.state().source,'');assert.equal(t.state().busy,false);assert.match(t.app.walletError,/order changed/);
    }
});
