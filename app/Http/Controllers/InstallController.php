<?php

namespace App\Http\Controllers;

use App\Models\Tenants;
use Illuminate\Http\Request;

class InstallController extends Controller
{
    protected $scopes = 'read_products,write_products';
    protected $accessMode = 'offline';
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $shop = request()->get('shop');
        if (Tenants::where('domain', $shop)->exists()) {
            return view('home');
        } else {
            $baseUrl = config('app.url');
            $clientId = env('SHOPIFY_CLIENT_ID');
            $redirectUrl = $baseUrl . '/redir';
            $nonce = bin2hex(random_bytes(12));
            $oauth_url = 'https://' . $shop . '/admin/oauth/authorize?client_id=' . $clientId . '&scope=' . $this->scopes . '&redirect_uri=' . urlencode($redirectUrl) . '&state=' . $nonce . '&grant_options[]=' . $this->accessMode;
            return redirect()->to($oauth_url);
        }
    }
}
