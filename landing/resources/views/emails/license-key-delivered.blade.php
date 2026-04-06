<x-mail::message>
@if ($order->locale === 'tr')
# Ödemeniz tamamlandı

**{{ $order->product->name }}** için lisans anahtarınız:

<x-mail::panel>
<code style="word-break: break-all;">{{ $license->license_key }}</code>
</x-mail::panel>

Bu anahtarı Hostvim panelinde **Ayarlar → Lisans** (veya kurulum sihirbazı) bölümüne girin.

Sipariş referansı: `{{ $order->order_ref }}`

Teşekkürler,<br>
{{ config('app.name') }}
@else
# Payment complete

Your license key for **{{ $order->product->name }}**:

<x-mail::panel>
<code style="word-break: break-all;">{{ $license->license_key }}</code>
</x-mail::panel>

Enter this key in the Hostvim panel under **Settings → License** (or the setup wizard).

Order reference: `{{ $order->order_ref }}`

Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message>
