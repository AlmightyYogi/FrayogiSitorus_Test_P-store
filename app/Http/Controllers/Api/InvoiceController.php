<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    private $tripayKey = 'DEV-kLqIybgYpiGzq1b9Hc3Nao42sNoM6QD5D8F5MrOZ';
    private $tripayUrl = 'https://tripay.co.id/api-sandbox/transaction/create';

    public function store(Request $request) 
{
    // Validation
    $request->validate([
        'product_id' => 'required|exists:products,id',
        'buyer_email' => 'required|email',
        'buyer_phone' => 'required|string',
    ]);

    $product = Product::findOrFail($request->product_id);
    $reference = Str::uuid()->toString();

    // Generate Signature
    $apiKey = 'your_api_key';
    $privateKey = 'your_private_key';
    $merchantCode = 'your_merchant_code';
    $amount = $product->price;
    
    // Signature generation using HMAC-SHA256
    $signature = hash_hmac('sha256', $merchantCode . $reference . $amount, $privateKey);

    // Hit API Tripay with Method and Signature
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->tripayKey,
    ])->post($this->tripayUrl, [
        'method' => 'BRIVA', // Metode pembayaran
        'amount' => $product->price,
        'sku' => $product->sku,
        'buyer_email' => $request->buyer_email,
        'buyer_phone' => $request->buyer_phone,
        'reference' => $reference,
        'signature' => $signature, // Signature added here
    ]);

    $data = $response->json();

    // Save response to Database
    $invoice = Invoice::create([
        'product_id' => $product->id,
        'tripay_reference' => $data['reference'] ?? $reference,
        'buyer_email' => $request->buyer_email,
        'buyer_phone' => $request->buyer_phone,
        'raw_response' => json_encode($data),
    ]);

    return response()->json($invoice, 201);
}

    public function index()
    {
        return Invoice::with('product')->get();
    }
}
