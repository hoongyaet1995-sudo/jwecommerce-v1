<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ShopBrandingController extends Controller
{
    public function show()
    {
        $profile = data_get(StoreSetting::where('key', 'shop_profile')->value('value'), 'data');
        if (!$profile) {
            $profile = array_fill_keys(['tagline', 'businessRegNo', 'sstNumber', 'email', 'phone', 'whatsapp', 'address', 'city', 'state', 'country', 'postcode', 'shopLogo', 'faviconUrl'], '');
            $profile['shopName'] = app(\App\Support\ShopBrand::class)->name();
        }
        return response()->json(['data' => $profile]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'profile' => ['required', 'array'],
            'profile.shopName' => ['required', 'string', 'max:120'],
            'profile.shopLogo' => ['nullable', 'string', 'max:500'],
            'logoPng' => ['nullable', 'string', 'max:7000000'],
            'iconPng' => ['required_with:logoPng', 'nullable', 'string', 'max:1000000'],
        ]);
        $profile = [];
        foreach (['shopName', 'tagline', 'businessRegNo', 'sstNumber', 'email', 'phone', 'whatsapp', 'address', 'city', 'state', 'country', 'postcode'] as $field) {
            $value = $request->input('profile.'.$field) ?? '';
            if (!is_string($value) || mb_strlen($value) > 2000) {
                throw ValidationException::withMessages(['profile.'.$field => 'Please enter no more than 2000 characters.']);
            }
            $profile[$field] = $value;
        }
        $paths = [];
        try {
            if (!empty($data['logoPng'])) {
                $logo = $this->png($data['logoPng'], 1024);
                $icon = $this->png($data['iconPng'], 256);
                $size = getimagesizefromstring($icon);
                if ($size[0] !== 256 || $size[1] !== 256) {
                    throw ValidationException::withMessages(['iconPng' => 'The favicon must be 256 by 256 pixels.']);
                }
                $id = (string) \Illuminate\Support\Str::uuid();
                $paths = ['branding/'.$id.'.png', 'branding/'.$id.'.ico'];
                // ICO supports a PNG-compressed 256px image (zero width/height denotes 256).
                $ico = pack('vvv', 0, 1, 1).pack('CCCCvvVV', 0, 0, 0, 0, 1, 32, strlen($icon), 22).$icon;
                foreach ([$logo, $ico] as $index => $bytes) {
                    if (!Storage::disk('public')->put($paths[$index], $bytes)) {
                        throw new \RuntimeException('Could not save the brand image.');
                    }
                }
                $profile['shopLogo'] = '/storage/'.$paths[0];
                $profile['faviconUrl'] = '/storage/'.$paths[1];
            }
            DB::transaction(function () use (&$profile): void {
                $old = StoreSetting::where('key', 'shop_profile')->lockForUpdate()->first();
                if (!isset($profile['shopLogo'])) {
                    $profile['shopLogo'] = data_get($old?->value, 'data.shopLogo', '');
                    $profile['faviconUrl'] = data_get($old?->value, 'data.faviconUrl', '');
                }
                StoreSetting::updateOrCreate(['key' => 'shop_profile'], ['value' => ['data' => $profile]]);
                StoreSetting::updateOrCreate(['key' => 'shop_name'], ['value' => ['data' => $profile['shopName']]]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }
        return response()->json(['data' => $profile]);
    }

    private function png(string $encoded, int $max): string
    {
        $bytes = str_starts_with($encoded, 'data:image/png;base64,') ? base64_decode(substr($encoded, 22), true) : false;
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] > $max || $size[1] > $max || !str_ends_with($bytes, "\x00\x00\x00\x00IEND\xaeB`\x82")) {
            throw ValidationException::withMessages(['logoPng' => 'Please upload a valid PNG, JPG, SVG or WebP image.']);
        }
        return $bytes;
    }
}
