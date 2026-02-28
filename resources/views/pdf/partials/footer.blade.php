                @php
                    $paymentFooterText = trim((string) \App\Models\SiteOption::value('document-footer-payment'));
                    $termsFooterText = trim((string) \App\Models\SiteOption::value('document-footer-terms'));
                    $travelFooterText = trim((string) \App\Models\SiteOption::value('document-footer-travel'));
                    $questionsFooterText = trim((string) \App\Models\SiteOption::value('document-footer-questions'));
                    $bankReferenceText = trim((string) \App\Models\SiteOption::value('document-footer-bank-reference'));
                    $bankAccountName = trim((string) \App\Models\SiteOption::value('payments.bank_account_name'));
                    $bankBsb = trim((string) \App\Models\SiteOption::value('payments.bank_bsb'));
                    $bankAccountNumber = trim((string) \App\Models\SiteOption::value('payments.bank_account_number'));
                @endphp
                <table class="footer">
                    <tr>
                        <td>
                            @if($paymentFooterText !== '')
                                <div class="block"><span class="heading">Payment</span> {{ $paymentFooterText }}</div>
                            @endif
                            @if($termsFooterText !== '')
                                <div class="block"><span class="heading">Terms</span> {{ $termsFooterText }}</div>
                            @endif
                        </td>
                        <td>
                            @if($travelFooterText !== '')
                                <div class="block"><span class="heading">Travel</span> {{ $travelFooterText }}</div>
                            @endif
                            @if($questionsFooterText !== '')
                                <div class="block"><span class="heading">Questions</span> {{ $questionsFooterText }}</div>
                            @endif
                            @if(isset($publicPayUrl) && $publicPayUrl !== '')
                            <div class="block"><span class="heading">Pay Online</span> visit <a href="{{ $publicPayUrl }}">{{ $displayPublicPayUrl }}</a> to pay this invoice online.</div>
                            @endif
                        </td>
                        <td>
                            @if($bankReferenceText !== '' || $bankAccountName !== '' || $bankBsb !== '' || $bankAccountNumber !== '')
                                <div class="block">
                                    <div class="heading">Bank Details</div>
                                    @if($bankReferenceText !== '')
                                        <div>{{ $bankReferenceText }}</div>
                                    @endif
                                    <table class="bank-table">
                                        @if($bankAccountName !== '')
                                            <tr>
                                                <th>Name</th>
                                                <td>{{ $bankAccountName }}</td>
                                            </tr>
                                        @endif
                                        @if($bankBsb !== '')
                                            <tr>
                                                <th>BSB</th>
                                                <td>{{ $bankBsb }}</td>
                                            </tr>
                                        @endif
                                        @if($bankAccountNumber !== '')
                                            <tr>
                                                <th>Account</th>
                                                <td>{{ $bankAccountNumber }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                </div>
                            @endif
                            <div class="thanks">Thank you for choosing STEMMechanics.</div>
                        </td>
                    </tr>
                </table>
