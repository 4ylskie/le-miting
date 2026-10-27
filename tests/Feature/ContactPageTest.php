<?php

use App\Mail\ContactEnquiryMail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

function contactXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

test('Contact shows the configured phone email and address without actionable placeholder links', function () {
    config([
        'restaurant.name' => 'Le Miting',
        'restaurant.is_demo' => true,
        'restaurant.phone' => '+60 12-345 6789',
        'restaurant.public_email' => 'contact@example.com',
        'restaurant.email' => 'private-recipient@example.org',
        'restaurant.address' => 'Jalan Ilmiah, Taman Universiti, 43000 Bangi, Selangor',
        'restaurant.opening_hours' => [1 => ['open' => '08:00', 'close' => '16:00']],
    ]);

    $response = $this->get(route('contact.create'))->assertOk()
        ->assertSee('Le Miting')
        ->assertDontSee('not an operating restaurant')->assertDontSee('academic demonstration')->assertDontSee('academic prototype')->assertSee('8:00 am – 4:00 pm')
        ->assertSee('Closed')->assertDontSee('private-recipient@example.org');
    $xpath = contactXPath($response->getContent());

    foreach ([
        'Contact Number' => '+60 12-345 6789',
        'Email' => 'contact@example.com',
    ] as $label => $value) {
        expect($xpath->evaluate('normalize-space(string(//main//dt[normalize-space()="'.$label.'"]/following-sibling::dd[1]))'))
            ->toBe($value);
    }
    expect($xpath->evaluate('normalize-space(string(//main//address))'))
        ->toBe('Jalan Ilmiah, Taman Universiti, 43000 Bangi, Selangor');

    expect($xpath->query('//main//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:")]')->length)->toBe(0);
    expect($xpath->query('//main//*[@id="contact-rasa-heading" or @id="rasa-ai-form"]')->length)->toBe(0);
    expect($xpath->query('//form[@id="contact-form" and @method="POST"]')->length)->toBe(1);
    expect($xpath->evaluate('string(//form[@id="contact-form"]/@action)'))->toBe(route('contact.store'));
    expect($xpath->query('//form[@id="contact-form"]//input[@name="_token"]')->length)->toBe(1);
    expect($xpath->query('//form[@id="contact-form"]//label')->length)->toBe(3);
});

test('non-demo configured contact details support telephone and email links', function () {
    config([
        'restaurant.is_demo' => false,
        'restaurant.phone' => '+60 12-345 6789',
        'restaurant.public_email' => 'mika25@gmail.com',
    ]);

    $this->get(route('contact.create'))->assertSee('href="tel:+60123456789"', false)
        ->assertSee('href="mailto:mika25@gmail.com"', false);
});

test('placeholder contact values remain non-actionable even outside demo mode', function () {
    config([
        'restaurant.is_demo' => false,
        'restaurant.phone' => 'not-a-phone',
        'restaurant.public_email' => 'contact@example.com',
    ]);

    $xpath = contactXPath($this->get(route('contact.create'))->getContent());

    expect($xpath->query('//main//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:")]')->length)->toBe(0);
});

test('Contact displays the configured Google map embed', function () {
    config([
        'restaurant.name' => 'Le Miting',
        'restaurant.is_demo' => true,
        'restaurant.maps.embed_key' => 'fake-embed-key',
        'restaurant.maps.query' => 'Demo location & test + reference',
    ]);

    $response = $this->get(route('contact.create'))->assertOk();
    $xpath = contactXPath($response->getContent());
    expect($xpath->query('//main//iframe')->length)->toBe(1);
    $map = $xpath->query('//main//iframe')->item(0);
    parse_str((string) parse_url($map->getAttribute('src'), PHP_URL_QUERY), $query);

    expect($map->getAttribute('title'))->toBe('Le Miting location map')
        ->and($query)->toMatchArray([
            'key' => 'fake-embed-key',
            'q' => 'Demo location & test + reference',
        ]);
});

test('Contact uses its configured fallback map when Maps settings are missing', function (?string $key, ?string $query) {
    config(['restaurant.maps.embed_key' => $key, 'restaurant.maps.query' => $query]);

    $response = $this->get(route('contact.create'))->assertOk();
    $xpath = contactXPath($response->getContent());
    expect($xpath->query('//main//iframe')->length)->toBe(1);
    $map = $xpath->query('//main//iframe')->item(0);

    expect($map->getAttribute('src'))->toContain('https://www.google.com/maps/d/u/0/embed?mid=')
        ->and($xpath->query('//form[@id="contact-form"]')->length)->toBe(1);
})->with([
    'missing key' => [null, 'Demo query'],
    'missing location' => ['fake-embed-key', null],
    'empty settings' => ['', ''],
    'whitespace settings' => [' ', ' '],
]);

test('Contact and reservation slots consume the same weekly hours', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 07:00:00', 'Asia/Kuala_Lumpur'));
    config(['restaurant.opening_hours' => [3 => ['open' => '09:00', 'close' => '12:00']]]);

    $this->get(route('contact.create'))->assertSee('9:00 am – 12:00 pm');
    $this->getJson(route('reservation.slots', ['date' => '2026-10-07']))
        ->assertExactJson(['slots' => ['09:00', '09:30', '10:00']]);
});

test('Contact does not invent hours when the shared schedule is unconfigured', function () {
    config(['restaurant.opening_hours' => null]);

    $this->get(route('contact.create'))->assertSee('Not configured')->assertDontSee('8:00 am');
});

test('enquiries still reach the existing recipient without Maps configuration', function () {
    Mail::fake();
    config([
        'restaurant.maps.embed_key' => '',
        'restaurant.maps.query' => '',
        'restaurant.public_email' => 'contact@example.com',
        'restaurant.email' => 'recipient@example.org',
    ]);

    $this->post(route('contact.store'), [
        'name' => 'Contact Test', 'email' => 'visitor@example.com', 'message' => 'A project enquiry.',
    ])->assertRedirect(route('contact.create'))->assertSessionHas('success');

    Mail::assertSent(ContactEnquiryMail::class, fn (ContactEnquiryMail $mail) => $mail->hasTo('recipient@example.org') && $mail->name === 'Contact Test'
        && $mail->email === 'visitor@example.com' && $mail->enquiry === 'A project enquiry.');
});
