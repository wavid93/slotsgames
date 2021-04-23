<?php

namespace App\Http\Controllers\FreeSlots;

use App\Facades\PathManager;
use App\Http\Controllers\Controller;
use App\Repositories\Genesis\FilterAttribute\Repository as FilterAttributeRepo;
use App\Repositories\Genesis\ProductFeature\Repository as ProductFeatureRepo;
use App\Repositories\Genesis\SiteBrandProduct\Repository as SiteBrandProductRepo;
use App\Repositories\Genesis\SiteOffer\Repository as SiteOfferRepo;
use Illuminate\Support\Facades\App;

class FreeSlotsController extends Controller
{
	public function __invoke()
	{
		$this->productFeatureRepo = App::make(ProductFeatureRepo::class);
		$this->siteBrandProductRepo = App::make(SiteBrandProductRepo::class);
		$this->siteOfferRepo = App::make(SiteOfferRepo::class);
		$this->filterAttributeRepo = App::make(FilterAttributeRepo::class);

		$productFeatures = $this->productFeatureRepo->withParameters([
			'free_demo' => true,
			'feature_type_name' => 'Slot Games',
			'page_size' => 1000,
			'order_by[feature_name]' => 'asc',
			'page' => 1,
			'select' => 'id,feature_name,slot_paylines,slot_reels,slots_wilds_symbol,slot_progressive,slot_random_jackpot,slot_bonus_multipliers,autoplay_option,slots_scatters,three_d,fruit_machine_slot,video_slot,free_game_url,free_mobile_game_url',
			'with' => 'software,brand_products',
		])->all();

		$siteBrandProducts = $this->siteBrandProductRepo->withParameters([
			'publish' => 1,
			'page_size' => 100,
			'site_country_version_id' => localConfig('site_country_version'),
			'product_type_id' => 34, // Casino
			'order_by[product_ranking]' => 'asc',
			'select' => 'id,brand_product_id',
			'with' => 'brand_product,brand_product.brand,brand_product.product_type,site_offers,state_site_brand_products',
		])->all();

		$siteOffers = $this->siteOfferRepo->withParameters([
			'publish' => true,
			'page_size' => 100,
			'site_country_version_id' => localConfig('site_country_version'),
			'select' => 'id,bonus_description,offer_id,site_brand_product_id,default_site_offer',
			'with' => 'offer',
		])->all();

		foreach ($siteBrandProducts as $index => $product) {
			$siteOffer = $siteOffers->where('site_brand_product_id', $product->id)->where('default_site_offer', true)->first();
			$product->bonus_description = $siteOffer->bonus_description;
			$product->terms_and_conditions = $siteOffer->offer->terms_and_conditions;
			$product->site_offer_id = $siteOffer->id;
			$product->offer_id = $siteOffer->offer_id;
		}

		$filterAttributes = $this->filterAttributeRepo->withParameters([
			'publish' => true,
			'page' => 1,
			'order_by[order]' => 'asc',
			'page_size' => 100,
			'select' => 'id,name,knack_field,knack_value',
		])->all();

		$slots = $productFeatures->filter(function ($feature) use ($siteBrandProducts, $filterAttributes) {
			$product_names = array_column($feature->brand_products, 'product_name');
			$firstMatchingSiteBrandProduct = $siteBrandProducts->whereIn('brand_product.product_name', array_values($product_names))->first();
			if (!$firstMatchingSiteBrandProduct) {
				return false;
			}

			$feature->site_offer_id = $firstMatchingSiteBrandProduct->site_offer_id;
			$feature->offer_id = $firstMatchingSiteBrandProduct->offer_id;
			$feature->product_id = $firstMatchingSiteBrandProduct->id;
			$feature->operator = $firstMatchingSiteBrandProduct->brand_product->brand->name;
			$feature->product = $firstMatchingSiteBrandProduct->brand_product->product_name;
			$feature->brand_id = $firstMatchingSiteBrandProduct->brand_product->brand_id;
			$feature->brand_product_id = $firstMatchingSiteBrandProduct->brand_product->id;
			$feature->object_name = 'Slot Game Logo - 266px - ' . $feature->id;

			$filterAttributeIds = [];
			$filterAttributes->each(function ($filter) use (&$feature, &$filterAttributeIds) {
				if (isset($feature->{$filter->knack_field}) && $filter->knack_field !== 'software') {
					if ($feature->{$filter->knack_field} == $filter->knack_value) {
						$filterAttributeIds[] = $filter->id;
					}
				}

				if ($filter->knack_field == 'software') {
					if ($feature->software[0]->name == $filter->name) {
						$filterAttributeIds[] = $filter->id;
					}
				}
			});

			$feature->filter_attribute_ids = $filterAttributeIds;

			return true;
		});

		$objectUrls = getMultipleObjects($slots->pluck('object_name')->toArray());
		$exitLinkUrls = PathManager::generatePaths(array_values($slots->pluck('site_offer_id')->unique()->toArray()), localConfig('site') . ' - Site Offer - Click');
		foreach ($slots as $slot) {
			foreach ($objectUrls as $object_name => $image) {
				if ($slot->object_name === $object_name) {
					$slot->image = $image;
				}
			}
			foreach ($exitLinkUrls as $site_offer_id => $exit_link) {
				if ($slot->site_offer_id === $site_offer_id) {
					$slot->exit_link = $exit_link;
				}
			}
		}

	

		return response()->json([
			'slots' => $slots,
			'filterTypes' => $filterTypes,
		]);
	}
}
