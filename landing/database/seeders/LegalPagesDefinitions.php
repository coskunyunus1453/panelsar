<?php

namespace Database\Seeders;

/**
 * Hostvim landing — yasal / ticari şablon metinler (avukat onayı şarttır).
 *
 * @return list<array{locale: string, slug: string, title: string, meta_description: string, sort_order: int, content: string}>
 */
final class LegalPagesDefinitions
{
    public static function all(): array
    {
        return array_merge(
            self::turkishPages(),
            self::englishPages(),
        );
    }

    /**
     * @return list<array{locale: string, slug: string, title: string, meta_description: string, sort_order: int, content: string}>
     */
    private static function turkishPages(): array
    {
        $b = self::trBlock();

        return [
            self::row('tr', 'kvkk', 'KVKK Aydınlatma Metni', '6698 sayılı KVKK kapsamında kişisel verilerin işlenmesine ilişkin aydınlatma.', 31, $b->kvkk),
            self::row('tr', 'gizlilik-politikasi', 'Gizlilik Politikası', 'Web sitesi ve hizmet kullanımında kişisel verilerin korunması.', 32, $b->gizlilik),
            self::row('tr', 'cerez-politikasi', 'Çerez Politikası', 'Çerez türleri, amaçları ve tercih yönetimi.', 33, $b->cerez),
            self::row('tr', 'mesafeli-satis', 'Mesafeli Satış Sözleşmesi', '6502 sayılı Kanun ve Mesafeli Sözleşmeler Yönetmeliği kapsamı.', 34, $b->mesafeli),
            self::row('tr', 'kullanim-kosullari', 'Kullanım Koşulları', 'Yazılım, web sitesi ve hizmetlerin kullanım şartları.', 35, $b->kullanim),
            self::row('tr', 'sla', 'Hizmet Seviyesi (SLA)', 'Erişilebilirlik hedefleri, bakım ve destek çerçevesi.', 36, $b->sla),
            self::row('tr', 'iade-ve-iptal', 'Ücret İadesi ve İptal Koşulları', 'Cayma, iptal ve iade süreçleri.', 37, $b->iade),
            self::row('tr', 'veri-merkezi', 'Veri Merkezi ve Altyapı', 'Barındırma lokasyonu ve alt işlemci bilgisi.', 38, $b->veri),
            self::row('tr', 'musteri-sozlesmesi', 'Müşteri Hizmet Sözleşmesi', 'Lisans / SaaS hosting paneli hizmet sözleşmesi çerçevesi.', 39, $b->musteri),
        ];
    }

    /**
     * @return list<array{locale: string, slug: string, title: string, meta_description: string, sort_order: int, content: string}>
     */
    private static function englishPages(): array
    {
        $b = self::enBlock();

        return [
            self::row('en', 'kvkk', 'Privacy & data protection notice', 'How we process personal data in line with applicable law.', 31, $b->kvkk),
            self::row('en', 'gizlilik-politikasi', 'Privacy policy', 'How we collect, use, and protect personal data.', 32, $b->gizlilik),
            self::row('en', 'cerez-politikasi', 'Cookie policy', 'Cookies we use and how to manage preferences.', 33, $b->cerez),
            self::row('en', 'mesafeli-satis', 'Distance / online sales terms', 'Terms for online purchase of digital services or licenses.', 34, $b->mesafeli),
            self::row('en', 'kullanim-kosullari', 'Terms of service', 'Rules for using our website, software, and services.', 35, $b->kullanim),
            self::row('en', 'sla', 'Service level agreement (SLA)', 'Availability targets, maintenance, and support response goals.', 36, $b->sla),
            self::row('en', 'iade-ve-iptal', 'Refunds & cancellation', 'Cooling-off, cancellation, and refund rules.', 37, $b->iade),
            self::row('en', 'veri-merkezi', 'Data centre & infrastructure', 'Hosting location and subprocessors (summary).', 38, $b->veri),
            self::row('en', 'musteri-sozlesmesi', 'Customer agreement', 'Framework agreement for licensing / SaaS of the hosting control panel.', 39, $b->musteri),
        ];
    }

    /**
     * @return array{kvkk: string, gizlilik: string, cerez: string, mesafeli: string, kullanim: string, sla: string, iade: string, veri: string, musteri: string}
     */
    private static function trBlock(): object
    {
        $sirket = '[TİCARİ ÜNVAN]';
        $adres = '[ADRES]';
        $eposta = '[E-POSTA]';
        $mersis = '[MERSİS NO]';
        $vno = '[VERGİ KİMLİK / NO]';
        $not = '> **Önemli:** Bu metin bilgilendirme amaçlı şablondur. Şirket unvanı, adres, iletişim, ürün ve ödeme modelinize göre **mutlaka bir hukuk danışmanı** tarafından gözden geçirilmelidir.';

        return (object) [
            'kvkk' => <<<MD
{$not}

## Veri sorumlusu

**{$sirket}** (bundan böyle “Şirket”), 6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) kapsamında veri sorumlusudur.

- **Adres:** {$adres}
- **E-posta:** {$eposta}
- **MERSİS:** {$mersis} · **Vergi no:** {$vno}

## İşlenen kişisel veriler

Örnek kategoriler: kimlik / iletişim (ad, soyad, e-posta, telefon), müşteri işlem (sipariş, fatura, ödeme kaydı özetleri), teknik loglar (IP, tarayıcı, cihaz bilgisi, tarih-saat), destek talebi içerikleri, pazarlama izinleri (varsa).

## İşleme amaçları

Hizmetin sunulması ve sözleşmenin ifası; müşteri desteği; faturalandırma ve muhasebe; güvenlik ve kötüye kullanımın önlenmesi; yasal yükümlülüklerin yerine getirilmesi; (açık rızanız varsa) pazarlama ve iletişim.

## Hukuki sebepler

KVKK m.5/2 (c) sözleşmenin kurulması veya ifası; (ç) veri sorumlusunun hukuki yükümlülüğü; (f) meşru menfaat; (a) açık rıza (pazarlama çerezleri / bülten vb. için).

## Aktarım

Hizmetin gerektirdiği ölçüde; barındırma / ödeme / e-posta sağlayıcıları gibi **hizmet sağlayıcılarına** (yurt içi/yurt dışı, KVKK ve sözleşmelere uygun) aktarım yapılabilir. Yurt dışına aktarımda KVKK’da öngörülen şartlar uygulanır.

## Saklama süresi

İlgili mevzuatta öngörülen süreler ve meşru menfaat / sözleşme gereği gerekli süre boyunca; süre sonunda silme, yok etme veya anonimleştirme.

## Haklarınız

KVKK m.11 kapsamında; verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, düzeltme/silme, itiraz, zararın giderilmesi talebi vb. **{$eposta}** üzerinden başvurabilirsiniz. Şikâyet için Kişisel Verileri Koruma Kurulu’na başvuru hakkınız saklıdır.

**Son güncelleme:** {DATE}
MD,
            'gizlilik' => <<<MD
{$not}

Bu politika, **Hostvim** markası altında sunulan web sitesi, demo, iletişim formları ve bağlantılı dijital hizmetler için geçerlidir.

## Toplanan bilgiler

Formlar, hesap oluşturma, destek talepleri, çerezler ve sunucu logları aracılığıyla toplanan veriler (kimlik/iletişim, teknik veriler, kullanım istatistikleri).

## Kullanım amaçları

Hizmet sunumu, güvenlik, analitik (anonim/aggregate), iletişim, yasal uyum.

## Üçüncü taraflar

Barındırma, CDN, analitik, ödeme ve e-posta sağlayıcıları. Listeler sözleşme ekinde veya talep üzerine güncellenir.

## Güvenlik

Şifreleme (TLS), erişim kontrolleri ve sınırlı yetkilendirme prensipleri uygulanır; mutlak güvenlik taahhüdü verilmez.

## Haklar ve iletişim

KVKK başvuruları **{$eposta}** üzerinden. Politika güncellenebilir; önemli değişiklikler sitede duyurulur.

**Son güncelleme:** {DATE}
MD,
            'cerez' => <<<MD
{$not}

## Çerez nedir?

Çerezler, cihazınıza kaydedilen küçük metin dosyalarıdır.

## Kullandığımız çerez türleri

- **Zorunlu:** Oturum, güvenlik, dil tercihi.
- **İşlevsel:** Form ve tercih hatırlama.
- **Analitik:** Ziyaret istatistikleri (anonimleştirilmiş olabilir).
- **Pazarlama:** (Yalnızca açık rıza ile) yeniden pazarlama.

## Yönetim

Tarayıcı ayarlarından çerezleri silebilir veya engelleyebilirsiniz. Zorunlu çerezleri kapatmak bazı özellikleri etkileyebilir.

**Son güncelleme:** {DATE}
MD,
            'mesafeli' => <<<MD
{$not}

## Taraflar

**SATICI:** {$sirket}, {$adres}, {$eposta}

**ALICI:** Sipariş sırasında bildirdiği bilgilerle tanımlanan gerçek/tüzel kişi.

## Konu

Dijital ürün / lisans / abonelik (hosting paneli yazılımı ve ilişkili hizmetler) satışına ilişkin mesafeli sözleşme hükümleri.

## Cayma hakkı

Mesafeli Sözleşmeler Yönetmeliği kapsamında, **elektronik ortamda anında ifa edilen** veya dijital içerikte tüketicinin onayı ile ifaya başlanan hizmetlerde cayma hakkı istisnaları bulunabilir. Gerçek uygulama ürün tipinize (lisans, kurulum, SaaS) göre hukukçunuzca netleştirilmelidir.

## Ödeme ve fiyat

Fiyatlar sitede veya teklifte belirtilir; KDV ve yasal kesintiler ayrıca gösterilir.

## Uyuşmuzluk

Tüketici işlemlerinde Tüketici Hakem Heyeti / Tüketici Mahkemeleri yetkilidir (mevzuata göre).

**Son güncelleme:** {DATE}
MD,
            'kullanim' => <<<MD
{$not}

## Kapsam

Web sitesi, dokümantasyon ve **Hostvim** hosting kontrol paneli yazılımının kullanımına ilişkin şartlar.

## Lisans

Yazılım, satın alınan lisans tipine (ör. tek sunucu, vendor) göre kullanılır. Kaynak kodu, tersine mühendislik, lisans dışı çoğaltma yasaktır (sözleşme ve lisans metnine tabi).

## Kabul edilebilir kullanım

Yasadışı içerik barındırma, spam, güvenlik açığı taraması (izinsiz), başkalarının sistemlerine zarar verme yasaktır. İhlal halinde hizmet askıya alınabilir veya feshedilebilir.

## Sorumluluk reddi

Yazılım “olduğu gibi” sunulur; iş sürekliliği ve üçüncü taraf hizmetlerinden doğan dolaylı zararlar için sorumluluk, mevzuatın izin verdiği azami ölçüde sınırlıdır.

## Değişiklik

Şartlar güncellenebilir; yayın tarihi sitede belirtilir.

**Son güncelleme:** {DATE}
MD,
            'sla' => <<<MD
{$not}

## Hedefler (örnek — gerçek rakamları sözleşmede netleştirin)

- **Aylık erişilebilirlik hedefi:** %99,5 (planlı bakım hariç, aşağıda).
- **Planlı bakım:** Hafta içi [SAAT ARALIĞI], önceden [X] saat/gün bildirim (mümkün olduğunca).
- **Destek ilk yanıt hedefi:** İş günü içinde [X] saat (e-posta / ticket kanalı).

## Kapsam dışı

Müşteri kodu, üçüncü taraf eklentileri, DNS/ISP kesintileri, DDoS ve müşteri kaynaklı yapılandırma hataları.

## Kredi / tazminat

SLA ihlali halinde tazminat veya hizmet kredisi yalnızca **yazılı sözleşmede** açıkça düzenlenmişse geçerlidir.

**Son güncelleme:** {DATE}
MD,
            'iade' => <<<MD
{$not}

## Genel

Ödeme tipi (kart, havale, fatura) ve ürün (lisans, kurulum, aylık SaaS) modelinize göre iade kuralları değişir; aşağıdaki çerçeve şablondur.

## Cayma ve iptal

Tüketici işlemlerinde mevzuattaki cayma süreleri uygulanır; dijital içerik / anında ifa istisnaları için Mesafeli Sözleşmeler Yönetmeliği’ne uyulur.

## Kurumsal / B2B

Cayma hakkı olmayan sözleşmelerde iptal, sözleşme feshi hükümlerine tabidir.

## İade süreci

Talepler **{$eposta}** ile yapılır; uygun görülen ödemeler [X] iş günü içinde aynı kanala iade edilir (banka süreleri hariç).

**Son güncelleme:** {DATE}
MD,
            'veri' => <<<MD
{$not}

## Lokasyon

Müşteri verileri ve yedeklerin tutulduğu birincil bölge: **[ÜLKE / ŞEHİR veya bulut bölgesi]** (örn. Avrupa Birliği içi veri merkezi).

## Alt işlemciler

Barındırma, yedekleme, izleme ve e-posta için sınırlı erişimli alt işlemciler kullanılabilir. Güncel liste talep üzerine veya müşteri sözleşmesi ekinde paylaşılır.

## Güvenlik önlemleri

Erişim kontrolü, şifreleme, günlükleme ve yedekleme politikaları uygulanır.

**Son güncelleme:** {DATE}
MD,
            'musteri' => <<<MD
{$not}

## Taraflar ve tanımlar

**Sağlayıcı:** {$sirket}  
**Müşteri:** Lisans veya hizmet sözleşmesini onaylayan taraf.

## Hizmetin kapsamı

Hostvim hosting kontrol paneli yazılımının sağlanması, güncellemeler (lisansa bağlı) ve belirlenen destek kanalları.

## Ücretlendirme ve ödeme

Plan, lisans veya teklif ekindeki fiyatlandırma geçerlidir; gecikmede fesih ve faiz hakları sözleşmede düzenlenir.

## Hizmetin askıya alınması

Ödeme gecikmesi, yasadışı kullanım veya güvenlik riski halinde geçici askıya alma.

## Gizlilik ve veri işleme

Kişisel veriler KVKK Aydınlatma Metni ve Gizlilik Politikası’na uygun işlenir.

## Süre ve fesih

Sözleşme süresi ve yenileme koşulları sipariş formunda; fesih bildirim süreleri sözleşmede belirtilir.

## Uygulanacak hukuk ve yetki

**[TÜRKİYE / İSTANBUL]** (örnek) — hukukçunuzca güncellenmelidir.

**Son güncelleme:** {DATE}
MD,
        ];
    }

    /**
     * @return object{kvkk: string, gizlilik: string, cerez: string, mesafeli: string, kullanim: string, sla: string, iade: string, veri: string, musteri: string}
     */
    private static function enBlock(): object
    {
        $company = '[LEGAL ENTITY NAME]';
        $addr = '[ADDRESS]';
        $mail = '[EMAIL]';
        $not = '> **Important:** This is a template for information only. Have it reviewed by qualified legal counsel for your entity, product, and jurisdiction.';

        return (object) [
            'kvkk' => <<<MD
{$not}

## Controller

**{$company}** (“we”, “us”) is the controller of personal data for this website and related services.

- **Address:** {$addr}
- **Contact:** {$mail}

## Data we process

Examples: identity/contact details, account and billing metadata, technical logs (IP, user agent), support messages, and—if you consent—marketing preferences.

## Purposes and legal bases

Service delivery (contract), legal obligations, legitimate interests (security, analytics in aggregated form), and consent where required (e.g. non-essential cookies / newsletters).

## Recipients

Hosting, payment, email, and analytics providers acting as processors/sub-processors, including transfers outside your country where legally permitted and safeguarded.

## Retention

As required by law and as long as necessary for the purposes described, then deleted or anonymised.

## Your rights

Depending on applicable law, you may request access, rectification, erasure, restriction, portability, or object to processing. Contact **{$mail}**. You may lodge a complaint with your supervisory authority.

**Last updated:** {DATE}
MD,
            'gizlilik' => <<<MD
{$not}

This policy describes how **Hostvim** collects and uses personal data when you use our website, demos, and related digital services.

## What we collect

Information you submit in forms, account creation, support tickets, cookies, and server logs.

## How we use it

To provide the service, secure our systems, analyse aggregated usage, communicate with you, and comply with law.

## Sharing

With infrastructure, payment, email, and analytics vendors under appropriate agreements.

## Security

We apply technical and organisational measures (e.g. TLS, access control). No method is 100% secure.

## Contact

**{$mail}** · {$addr}

**Last updated:** {DATE}
MD,
            'cerez' => <<<MD
{$not}

## What are cookies?

Small text files stored on your device.

## Types

Strictly necessary, functional, analytics, and—only with consent—marketing.

## Managing cookies

You can block or delete cookies in your browser. Disabling strictly necessary cookies may break parts of the site.

**Last updated:** {DATE}
MD,
            'mesafeli' => <<<MD
{$not}

## Parties

**Seller:** {$company}, {$addr}, {$mail}  
**Buyer:** The person or entity identified in the order.

## Subject

Online purchase of digital services or software licenses related to the Hostvim hosting control panel.

## Withdrawal / cooling-off

Rules depend on your jurisdiction and whether delivery is instant digital content. Many laws exclude or limit withdrawal once performance has started with the buyer’s consent—confirm with counsel.

## Price and taxes

As shown at checkout or in the written quote, including applicable taxes.

## Disputes

As specified under applicable consumer or commercial law.

**Last updated:** {DATE}
MD,
            'kullanim' => <<<MD
{$not}

## Scope

Use of the website, documentation, and Hostvim software under the purchased license.

## License

Use is limited to the purchased tier (e.g. per server, vendor). No reverse engineering, circumvention, or redistribution beyond the license.

## Acceptable use

No illegal content, spam, unauthorised intrusion attempts, or activities harming third parties. We may suspend or terminate for breach.

## Disclaimer

Software is provided as available; liability is limited to the extent permitted by law.

## Changes

We may update these terms; the publication date will be indicated.

**Last updated:** {DATE}
MD,
            'sla' => <<<MD
{$not}

## Targets (examples — fix in your contract)

- **Monthly availability target:** 99.5% excluding scheduled maintenance.
- **Scheduled maintenance:** Preferably off-peak with prior notice where practical.
- **First response target (business hours):** [X] hours via email/ticket.

## Exclusions

Customer code, third-party plugins, DNS/ISP issues, DDoS, and misconfiguration by the customer.

## Remedies

Service credits or penalties apply only if explicitly stated in a signed agreement.

**Last updated:** {DATE}
MD,
            'iade' => <<<MD
{$not}

## General

Refund rules depend on payment method and product type (perpetual license, setup fee, monthly SaaS).

## Consumer rights

Local consumer laws may grant cooling-off rights with exceptions for digital content delivered immediately with consent.

## Business customers

Often governed by contract rather than consumer withdrawal rules.

## Process

Contact **{$mail}** with order details. Approved refunds are returned to the original payment method within [X] business days (bank timelines may apply).

**Last updated:** {DATE}
MD,
            'veri' => <<<MD
{$not}

## Location

Primary region for production data and backups: **[REGION / CLOUD AREA]** (e.g. EU).

## Subprocessors

Hosting, backups, monitoring, and email providers with limited access. An up-to-date list is available on request or in the data processing agreement.

## Security

Access controls, encryption in transit, logging, and backup policies.

**Last updated:** {DATE}
MD,
            'musteri' => <<<MD
{$not}

## Parties

**Provider:** {$company}  
**Customer:** The entity accepting the order or master agreement.

## Service

Provision of the Hostvim hosting control panel software, updates as covered by the license, and agreed support channels.

## Fees

Per order, quote, or subscription plan; late payment may trigger suspension as described in the agreement.

## Suspension

For non-payment, illegal use, or material security risk.

## Data protection

Processing of personal data follows our privacy notice and, where required, a data processing agreement.

## Term and termination

As set out in the order form or master agreement.

## Governing law

**[JURISDICTION]** — replace with counsel-approved wording.

**Last updated:** {DATE}
MD,
        ];
    }

    /**
     * @param  array{locale: string, slug: string, title: string, meta_description: string, sort_order: int, content: string}  $row
     */
    private static function row(string $locale, string $slug, string $title, string $meta, int $sort, string $content): array
    {
        $date = date('Y-m-d');

        return [
            'locale' => $locale,
            'slug' => $slug,
            'title' => $title,
            'meta_description' => $meta,
            'sort_order' => $sort,
            'content' => str_replace('{DATE}', $date, $content),
        ];
    }
}
