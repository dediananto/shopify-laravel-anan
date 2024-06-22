<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Products;
use App\Models\Tenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProductController extends Controller
{
    protected $tenant;
    protected $apiVersion;
    public function __construct()
    {
        $this->tenant = Tenants::first();
        $this->apiVersion = env('SHOPIFY_API_VERSION');
    }
    public function pushProduct(Request $request)
    {
        $input = $request->input('product');
        $checkProductExistUrl = 'https://'.$this->tenant->domain.'/admin/api/'.$this->apiVersion.'/graphql.json';
        $sku = $input['kode'];
        $query = <<<GRAPHQL
            query {
                products(first: 1, query: "sku:$sku") {
                    edges {
                        node {
                            legacyResourceId
                        }
                    }
                }
            }
        GRAPHQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' =>  $this->tenant->token,
            'Content-Type' => 'application/json' 
        ])->post($checkProductExistUrl, [
            'query' => $query,
        ]);
        if ($response->successful()) {
            $data = $response->json();
            $products = $data['data']['products']['edges'];
            $shopifyProductId = null;
            $method = 'POST';
            if (count($products) > 0) {
                foreach ($products as $product) {
                    $shopifyProductId = $product['node']['legacyResourceId'];
                }
                $method = 'PUT';
            }
            try {
                $shopifyProductId = $this->upsertShopifyProduct($input, $method, $shopifyProductId);
            } catch (\Exception $e) {
                return response(['message' => $e->getMessage()], 500);
            }

            Products::updateOrCreate(
                ["sku" => $sku],
                [
                    "sku" => $sku,
                    "data" => $input,
                    "shop_product_id" => $shopifyProductId
                ]
            );
            return response(['message' => 'success'], 200);
        }
    }

    private function upsertShopifyProduct($input, $method, $shopifyProductId = null)
    {
        $sku = $input['kode'];
        $status = $input['status'] == 'Enable' ? 'active' : 'draft';
        $images = [];
        foreach ($input['gambar'] as $gambar) {
            $images[] = [
                "src" => $gambar['image']
            ];
        }
        $payloadToShopify = [  
            "product" => [
                "title" => $input['nama'],
                "body_html" => $input['deskripsi'],
                "status" => $status,
                "variants" => [
                    [
                        "price" => $input['harga'],
                        "sku" => $sku,
                        "title" => $input['nama'],
                        "weight" => $input['berat'],
                        "weight_unit" => 'kg',
                    ]
                ],
                "images" => $images
            ]
        ];

        if ($method == 'PUT') {
            $url = 'https://'.$this->tenant->domain.'/admin/api/'.$this->apiVersion.'/products/'.$shopifyProductId.'.json';
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->tenant->token,
                'Content-Type' => 'application/json' 
            ])->put($url, $payloadToShopify);
        } else {
            $url = 'https://'.$this->tenant->domain.'/admin/api/'.$this->apiVersion.'/products.json';
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->tenant->token,
                'Content-Type' => 'application/json' 
            ])->post($url, $payloadToShopify);
        }

        if ($response->successful()) {
            $data = $response->json();
            return $data['product']['id'];
        } else {
            throw new \Exception($response->body());
        }
    }
}
