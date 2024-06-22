<?php

namespace App\Http\Controllers;

use App\Models\Tenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RedirController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $cliendId = env('SHOPIFY_CLIENT_ID');
        $secretKey = env('SHOPIFY_CLIENT_SECRET');
        $shop = $request->get('shop');
        $code = $request->get('code');

        $accessTokenEndpoint = 'https://' . $shop . '/admin/oauth/access_token';
        $data = array(
            "client_id" => $cliendId,
            "client_secret" => $secretKey,
            "code" => $code
        );
        $urlGetAccessToken = $accessTokenEndpoint.'?'.http_build_query($data);
        $response = Http::post($urlGetAccessToken);
        if ($response->ok()) {
            $result = $response->json();
            Tenants::updateOrCreate(
                ['domain' => $shop],
                [
                    'domain' => $shop,
                    'token' => $result['access_token']
                ]
            );
            return redirect()->to('https://'.$shop.'/admin/apps');
        }
    }
}
