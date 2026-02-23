                <table class="footer">
                    <tr>
                        <td>
                            <div class="block"><span class="heading">Payment</span> We accept cash, bank transfer and credit cards (Over the phone payments attract a 2.5% fee).</div>
                            <div class="block"><span class="heading">Terms</span> Payment terms are strictly 28 days from the invoice date. Long-term scheduled deliveries will be invoiced quarterly.</div>
                        </td>
                        <td>
                            <div class="block"><span class="heading">Travel</span> The first 30 minutes of travel is free; $28.00 every additional 15 minutes.</div>
                            <div class="block"><span class="heading">Questions</span> If you have any questions about this invoice, please feel free to contact us.</div>
                            @if(isset($publicPayUrl) && $publicPayUrl !== '')
                            <div class="block"><span class="heading">Pay Online</span> visit <a href="{{ $publicPayUrl }}">{{ $displayPublicPayUrl }}</a> to pay this invoice online.</div>
                            @endif
                        </td>
                        <td>
                            <div class="block">
                                <div class="heading">Bank Details</div>
                                <div>Please include the invoice number as the payment description.</div>
                                <table class="bank-table">
                                    <tr>
                                        <th>Name</th>
                                        <td>STEMMechanics</td>
                                    </tr>
                                    <tr>
                                        <th>BSB</th>
                                        <td>062-692</td>
                                    </tr>
                                    <tr>
                                        <th>Account</th>
                                        <td>732-6629</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="thanks">Thank you for choosing STEMMechanics.</div>
                        </td>
                    </tr>
                </table>
