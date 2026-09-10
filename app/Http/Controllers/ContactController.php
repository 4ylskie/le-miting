<?php

namespace App\Http\Controllers;

use App\Mail\ContactEnquiryMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function create()
    {
        return view('customer.contact.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

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
