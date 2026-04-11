<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table) {
            $table->string('label_en', 255)->nullable()->after('label');
            $table->string('href_en', 2048)->nullable()->after('href');
        });

        $byHref = [
            '/#features' => 'Features',
            '/pricing' => 'Pricing',
            '/setup' => 'Installation',
            '/docs' => 'Documentation',
            '/blog' => 'Blog',
            '/#faq' => 'FAQ',
            '/admin/login' => 'Admin login',
            '/p/kvkk' => 'Privacy notice',
            '/p/gizlilik-politikasi' => 'Privacy policy',
            '/p/cerez-politikasi' => 'Cookie policy',
            '/p/mesafeli-satis' => 'Distance sales',
            '/p/kullanim-kosullari' => 'Terms of use',
            '/p/sla' => 'SLA',
            '/p/iade-ve-iptal' => 'Refunds',
            '/p/veri-merkezi' => 'Data centre',
            '/p/musteri-sozlesmesi' => 'Customer agreement',
        ];

        foreach ($byHref as $href => $labelEn) {
            DB::table('nav_menu_items')->where('href', $href)->whereNull('label_en')->update(['label_en' => $labelEn]);
        }
    }

    public function down(): void
    {
        Schema::table('nav_menu_items', function (Blueprint $table) {
            $table->dropColumn(['label_en', 'href_en']);
        });
    }
};
