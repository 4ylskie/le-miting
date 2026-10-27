<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactEnquiryMail;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        $isDemo = (bool) config('restaurant.is_demo');
        $phone = (string) config('restaurant.phone');
        $email = (string) config('restaurant.public_email');
        $phoneNumber = preg_replace('/[\s().-]/', '', $phone);
        $mapKey = trim((string) config('restaurant.maps.embed_key'));
        $mapQuery = trim((string) config('restaurant.maps.query'));
        $hours = config('restaurant.opening_hours');
        $openingHours = [];

        foreach ([1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day => $label) {
            $window = $hours[$day] ?? null;
            $openingHours[$label] = $hours === null ? 'Not configured' : ($window === null ? 'Closed' :
                Carbon::createFromFormat('H:i', $window['open'])->format('g:i a').' – '.
                Carbon::createFromFormat('H:i', $window['close'])->format('g:i a'));
        }

        return view('customer.contact.create', [
            'restaurantName' => config('restaurant.name'),
            'isDemo' => $isDemo,
            'phone' => $phone,
            'email' => $email,
            'address' => config('restaurant.address'),
            'phoneLink' => ! $isDemo && preg_match('/^\+?[0-9]{7,15}$/', $phoneNumber) ? 'tel:'.$phoneNumber : null,
            'emailLink' => ! $isDemo && filter_var($email, FILTER_VALIDATE_EMAIL)
                && ! preg_match('/@(?:[^@]+\.)?example\.(?:com|net|org)$/i', $email) ? 'mailto:'.$email : null,
            'openingHours' => $openingHours,
            'mapUrl' => $mapKey !== '' && $mapQuery !== ''
                ? 'https://www.google.com/maps/embed/v1/place?'.http_build_query(['key' => $mapKey, 'q' => $mapQuery], '', '&', PHP_QUERY_RFC3986)
                : 'https://www.google.com/maps/d/u/0/embed?mid=1rPnhdqsij7VbYtNhzaS71RrLlxLTGv0&ehbc=2E312F',
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            Mail::to(config('restaurant.email'))
                ->send(new ContactEnquiryMail(
                    $validated['name'],
                    $validated['email'],
                    $validated['message'],
                ));

            return redirect()->route('contact.create')
                ->with('success', 'Thank you! Your enquiry has been sent. We will get back to you soon.');
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->with('error', 'Sorry, we could not send your message. Please try again.');
        }
    }
}
