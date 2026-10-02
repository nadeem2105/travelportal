<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_homepage_renders_with_sections(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Explore Kashmir');
        $response->assertSee('Popular Kashmir Tour Packages');
        $response->assertSee('What Our Travellers Say');
        $response->assertSee(settings('company_name', 'Leemroz Travels'));
    }

    public function test_disabled_homepage_section_is_hidden(): void
    {
        HomepageSection::where('key', 'testimonials')->update(['is_enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('What Our Travellers Say');
    }

    public function test_packages_listing_and_detail(): void
    {
        $this->get('/packages')->assertOk();

        $package = Package::where('status', 'active')->first();
        $this->get("/packages/{$package->slug}")
            ->assertOk()
            ->assertSee($package->name)
            ->assertSee('Book This Package');
    }

    public function test_destinations_guides_blog_pages_render(): void
    {
        $this->get('/destinations')->assertOk();
        $this->get('/guide')->assertOk();
        $this->get('/blog')->assertOk();
        $this->get('/faq')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/offers')->assertOk();
        $this->get('/p/about-us')->assertOk();
    }

    public function test_flight_search_returns_results(): void
    {
        Http::fake(); // block any outbound calls just in case

        $departure = now()->addDays(7)->toDateString();

        $response = $this->get('/flights/results?' . http_build_query([
            'from' => 'DEL', 'to' => 'SXR', 'departure' => $departure, 'travellers' => '2',
        ]));

        $response->assertOk();
        $response->assertSee('Select Flight');
    }

    public function test_flight_search_validates_input(): void
    {
        $this->get('/flights/results?from=DE&to=SXR&departure=tomorrow')
            ->assertSessionHasErrors(['from', 'departure']);
    }

    public function test_hotel_search_page(): void
    {
        $checkIn = now()->addDays(7)->toDateString();
        $checkOut = now()->addDays(9)->toDateString();

        $this->get("/hotels/search?destination=Srinagar&check_in={$checkIn}&check_out={$checkOut}&guests=2")
            ->assertOk();
    }

    public function test_contact_form_persists_message(): void
    {
        $this->post('/contact', [
            'name' => 'Curious George',
            'email' => 'george@example.com',
            'subject' => 'Kashmir in December',
            'message' => 'We are planning a family trip in December, can you help?',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', ['email' => 'george@example.com']);
    }

    public function test_newsletter_subscription(): void
    {
        $this->post('/newsletter/subscribe', ['email' => 'deals@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'deals@example.com']);
    }

    public function test_404_for_missing_page(): void
    {
        $this->get('/p/does-not-exist')->assertNotFound();
    }
}
