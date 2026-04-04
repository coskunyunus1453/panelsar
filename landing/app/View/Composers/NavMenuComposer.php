<?php

namespace App\View\Composers;

use App\Models\NavMenuItem;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NavMenuComposer
{
    public function compose(View $view): void
    {
        $header = NavMenuItem::query()->activeForZone(NavMenuItem::ZONE_HEADER)->get();
        $footer = NavMenuItem::query()->activeForZone(NavMenuItem::ZONE_FOOTER)->get();

        $view->with([
            'landingHeaderNav' => $header->isNotEmpty() ? $header : $this->legacyHeader(),
            'landingFooterNav' => $footer->isNotEmpty() ? $footer : $this->legacyFooter(),
        ]);
    }

    /**
     * @return Collection<int, NavMenuItem>
     */
    private function legacyHeader(): Collection
    {
        return collect([
            new NavMenuItem(['label' => landing_p('nav.features'), 'href' => '/#features', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('nav.pricing'), 'href' => '/pricing', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('nav.setup'), 'href' => '/setup', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('nav.docs'), 'href' => '/docs', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('nav.blog'), 'href' => '/blog', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('nav.faq'), 'href' => '/#faq', 'open_in_new_tab' => false]),
        ]);
    }

    /**
     * @return Collection<int, NavMenuItem>
     */
    private function legacyFooter(): Collection
    {
        return collect([
            new NavMenuItem(['label' => landing_p('footer.docs'), 'href' => '/docs', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('footer.blog'), 'href' => '/blog', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('footer.faq'), 'href' => '/#faq', 'open_in_new_tab' => false]),
            new NavMenuItem(['label' => landing_p('footer.admin'), 'href' => '/admin/login', 'open_in_new_tab' => false]),
        ]);
    }
}
