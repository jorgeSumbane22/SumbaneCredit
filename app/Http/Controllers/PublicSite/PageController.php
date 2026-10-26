<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\LoanProduct;
use App\Models\Testimonial;
use App\Services\SettingsService;
use App\Support\ProductCatalog;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    private function publishedProducts()
    {
        return LoanProduct::published()->with('currentVersion')->orderBy('name')->get();
    }

    public function home(): View
    {
        $products = $this->publishedProducts();

        return view('public.home', [
            'products' => $products,
            'catalog' => ProductCatalog::simulatorData($products),
            'faqs' => Faq::where('is_active', true)->orderBy('sort_order')->limit(6)->get(),
            'testimonials' => Testimonial::where('is_active', true)->latest()->limit(3)->get(),
            'heroTitle' => $this->settings->get('content.hero_title'),
            'heroSubtitle' => $this->settings->get('content.hero_subtitle'),
            'about' => $this->settings->get('content.about'),
        ]);
    }

    public function about(): View
    {
        return view('public.about', [
            'about' => $this->settings->get('content.about'),
            'mission' => $this->settings->get('content.mission'),
            'vision' => $this->settings->get('content.vision'),
            'values' => array_filter(array_map('trim', explode("\n", (string) $this->settings->get('content.values')))),
        ]);
    }

    public function products(): View
    {
        return view('public.products', ['products' => $this->publishedProducts()]);
    }

    public function product(LoanProduct $product): View
    {
        abort_unless($product->status === 'active' && $product->is_public && $product->currentVersion, 404);
        $product->load('currentVersion');

        return view('public.product', [
            'product' => $product,
            'version' => $product->currentVersion,
            'catalog' => ProductCatalog::simulatorData(collect([$product])),
        ]);
    }

    public function how(): View
    {
        return view('public.how', ['content' => $this->settings->get('content.how_it_works')]);
    }

    public function faq(): View
    {
        return view('public.faq', ['faqs' => Faq::where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function privacy(): View
    {
        return view('public.legal', ['title' => 'Política de Privacidade', 'content' => $this->settings->get('content.privacy_policy')]);
    }

    public function terms(): View
    {
        return view('public.legal', ['title' => 'Termos e Condições', 'content' => $this->settings->get('content.terms')]);
    }
}
