<?php

namespace App\Exports;

use App\Model\Area;
use App\Model\Channel;
use App\Model\Country;
use App\Model\CustomerCategory;
use App\Model\CustomerInfo;
use App\Model\Item;
use App\Model\ItemGroup;
use App\Model\ItemMajorCategory;
use App\Model\ItemUom;
use App\Model\PDPArea;
use App\Model\PDPChannel;
use App\Model\PDPCountry;
use App\Model\PDPCustomer;
use App\Model\PDPCustomerCategory;
use App\Model\PDPItem;
use App\Model\PDPItemGroup;
use App\Model\PDPItemMajorCategory;
use App\Model\PDPPromotionItem;
use App\Model\PDPPromotionOfferItem;
use App\Model\PDPRegion;
use App\Model\PDPRoute;
use App\Model\PDPSalesOrganisation;
use App\Model\PriceDiscoPromoPlan;
use App\Model\Region;
use App\Model\Route;
use App\Model\SalesOrganisation;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PricingExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $StartDate, $EndDate;

    public function __construct(String  $StartDate, String $EndDate)
    {
        $this->StartDate = $StartDate;
        $this->EndDate = $EndDate;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $start_date = $this->StartDate;
        $end_date = $this->EndDate;

        $pricing_query = PriceDiscoPromoPlan::where('use_for', "Pricing");

        if ($start_date != '' && $end_date != '') {
            if ($start_date == $end_date) {
                $pricing_query->whereDate('start_date', $start_date);
            } else {
                $pricing_query->where('start_date', '<=', $start_date)
                    ->where('end_date', '>=', $end_date);
            }
        }

        $pricing = $pricing_query->orderBy('name', 'asc')->get();

        $pricingCollection = new Collection();
        if (count($pricing)) {
            foreach ($pricing as $p) {

                $cusotmers              = array();
                $coutries               = array();
                $regions                = array();
                $areas                  = array();
                $routes                 = array();
                $sales_organisations    = array();
                $channels               = array();
                $cusotmer_categories    = array();
                $item_major_categories  = array();
                $item_groups            = array();
                $items                  = array();

                $type = "Slab";
                if ($p->type == 1) {
                    $type = "Normal";
                }

                $key_word = explode('/', $p->combination_key_value);

                if (in_array('Customer', $key_word)) {
                    $cusotmers = PDPCustomer::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Country', $key_word)) {
                    $coutries = PDPCountry::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Region', $key_word)) {
                    $regions = PDPRegion::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Area', $key_word)) {
                    $areas = PDPArea::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Route', $key_word)) {
                    $routes = PDPRoute::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Sales Organisation', $key_word)) {
                    $sales_organisations = PDPSalesOrganisation::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Customer Category', $key_word)) {
                    $cusotmer_categories = PDPCustomerCategory::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Major Category', $key_word)) {
                    $item_major_categories = PDPItemMajorCategory::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Item Group', $key_word)) {
                    $item_groups = PDPItemGroup::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Material', $key_word)) {
                    $items = PDPItem::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }
                if (in_array('Channel', $key_word)) {
                    $channels = PDPChannel::where('price_disco_promo_plan_id', $p->id)->get()->toArray();
                }

                $merge_data = array_merge(
                    $coutries,
                    $regions,
                    $areas,
                    $routes,
                    $sales_organisations,
                    $channels,
                    $cusotmer_categories,
                    $cusotmers,
                    $item_major_categories,
                    $item_groups,
                    $items
                );

                $count = count($merge_data);

                for ($i = 0; $i <= $count; $i++) {
                    if (
                        isset($coutries[$i]) ||
                        isset($regions[$i]) ||
                        isset($areas[$i]) ||
                        isset($routes[$i]) ||
                        isset($sales_organisations[$i]) ||
                        isset($channels[$i]) ||
                        isset($cusotmer_categories[$i]) ||
                        isset($cusotmers[$i]) ||
                        isset($item_major_categories[$i]) ||
                        isset($item_groups[$i]) ||
                        isset($items[$i])
                    ) {

                        $pricingCollection->push((object)[
                            'name'              => $p->name,
                            'start_date'        => $p->start_date,
                            'end_date'          => $p->end_date,
                            'combination'       => $p->combination_key_value,
                            'pricing_type'      => $type,
                            'staus'             => ($p->status == 1) ? "Active" : "Inactive",
                            'country'           =>  isset($coutries[$i]) ? $this->country($coutries[$i]['country_id']) : "",
                            'region'            =>  isset($regions[$i]) ? $this->region($regions[$i]['region_id']) : "",
                            'area'              =>  isset($areas[$i]) ? $this->area($areas[$i]['area_id']) : "",
                            'route'             =>  isset($routes[$i]) ? $this->route($routes[$i]['route_id']) : "",
                            'sales_org'         =>  isset($sales_organisations[$i]) ? $this->sales_organisation($sales_organisations[$i]['sales_organisation_id']) : "",
                            'channel'           =>  isset($channels[$i]) ? $this->channel($channels[$i]['channel_id']) : "",
                            'customer_category' =>  isset($cusotmer_categories[$i]) ? $this->customer_category($cusotmer_categories[$i]['cusotmer_category_id']) : "",
                            'customer'          => (isset($cusotmers[$i])) ? $this->customer($cusotmers[$i]['customer_id']) : "",
                            'mojor_category'    =>  isset($item_major_categories[$i]) ? $this->item_major_category($item_major_categories[$i]['item_major_category_id']) : "",
                            'item_group'        =>  isset($item_groups[$i]) ? $this->item_group($item_groups[$i]['item_group_id']) : "",
                            'item_code'         =>  isset($items[$i]) ? $this->item($items[$i]['item_id'], 'code') : "",
                            'item_name'         =>  isset($items[$i]) ? $this->item($items[$i]['item_id'], 'name') : "",
                            'item_uom'          =>  isset($items[$i]) ? $this->itemUom($items[$i]['item_uom_id']) : "",
                            'price'             =>  isset($items[$i]) ? $items[$i]['price'] : "",
                            // 'max_price'         =>  isset($items[$i]) ? $items[$i]['max_price'] : "",
                        ]);
                    }
                }
            }
        }
        return $pricingCollection;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Start Date',
            'End Date',
            'Combination',
            'Pricing Type',
            'Status',
            'Country',
            'Region',
            'Area',
            'Route',
            'Sales Organisation',
            'Channel',
            'Customer Category',
            'Customer',
            'Major Category',
            'Item Group',
            'Item Code',
            'Item Name',
            'Item Uom',
            'Price',
            'Max Price'
        ];
    }

    private function country($country_id)
    {
        $country = Country::find();
        if ($country) {
            return $country->name;
        }
    }

    private function region($region_id)
    {
        $region = Region::find($region_id);
        if ($region) {
            return $region->region_name;
        }
    }

    private function area($area_id)
    {
        $area = Area::find($area_id);
        if ($area) {
            return $area->area_name;
        }
    }

    private function route($route_id)
    {
        $route = Route::find($route_id);
        if ($route) {
            return $route->route_name;
        }
    }

    private function sales_organisation($sales_organisation_id)
    {
        $sales_organisation = SalesOrganisation::find($sales_organisation_id);
        if ($sales_organisation) {
            return $sales_organisation->name;
        }
    }

    private function channel($channel_id)
    {
        $channel = Channel::find($channel_id);
        if ($channel) {
            return $channel->name;
        }
    }

    private function customer_category($customer_category_id)
    {
        $customer_category = CustomerCategory::find($customer_category_id);
        if ($customer_category) {
            return $customer_category->customer_category_name;
        }
    }

    private function customer($customer_id)
    {
        $customer = CustomerInfo::where('id', $customer_id)->first();
        if ($customer) {
            return $customer->customer_code;
        }
    }

    private function item_major_category($item_major_category_id)
    {
        $item_major_category = ItemMajorCategory::find($item_major_category_id);
        if ($item_major_category) {
            return $item_major_category->name;
        }
    }

    private function item_group($item_group_id)
    {
        $item_group = ItemGroup::find($item_group_id);
        if ($item_group) {
            return $item_group->name;
        }
    }

    private function item($item_id, $type)
    {
        $item = Item::find($item_id);
        if ($item) {
            if ($type == "name") {
                return $item->item_name;
            }
            return $item->item_code;
        }
    }

    private function itemUom($item_uom_id)
    {
        $item_uom = ItemUom::find($item_uom_id);
        if ($item_uom) {
            return $item_uom->name;
        }
    }
}
