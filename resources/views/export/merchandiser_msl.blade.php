<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
            <title>Merchandiser MSL</title>
        </head>
        <body>           
            <table cellspacing="0" border="1">
                    <tr>
                        <table cellspacing="0" border="1">
                            <thead>
                                <tr>
                                    @if(count($merchandiser_id) > 0)
                                        <th style="min-width:50px">Merchandiser Name</th>
                                    @endif

                                    @if(count($customer_id))
                                        <th style="min-width:50px">Custmer Name</th>
                                    @endif
                                    <th style="min-width:50px">Total MSL</th>
                                    <th style="min-width:50px">MSL Check</th>
                                    <th style="min-width:50px">Percentage</th>
                                </tr>
                                </thead>
                                <tbody>
                @php
                    $merchatArray = [];
                @endphp
                @foreach($merchandiser_msls as $key=>$merchandiser)
                    @php 
                        $total_msl = 0;
                        $total_msl_check = 0;

                        $data = DB::table('merchandiser_msls')->where('merchandiser_id', $merchandiser->merchandiser_id)->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
                            $customer->whereIn('customer_id', $customer_id);

                        })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
                            $customer->whereIn('merchandiser_id', $merchandiser_id);

                        })->groupBy('customer_id')->get();

                    @endphp
                    @foreach($data as $value)
                        @php 
                            $customer_max_msl = DB::table('merchandiser_msls')->where('customer_id', $value->customer_id)->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
                                $customer->whereIn('customer_id', $customer_id);

                            })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
                                $customer->whereIn('merchandiser_id', $merchandiser_id);

                            })->max('total_msl_item');
                            $total_msl = $total_msl+$customer_max_msl;

                            $customer_max_msl_check = DB::table('merchandiser_msls')->where('customer_id', $value->customer_id)->whereBetween('date', [$start_date, $end_date])->when($customer_id!=null && count($customer_id)>0, function($customer) use ($customer_id){
                                $customer->whereIn('customer_id', $customer_id);
                            })->when($merchandiser_id!=null && count($merchandiser_id)>0, function($customer) use ($merchandiser_id){
                                $customer->whereIn('merchandiser_id', $merchandiser_id);
                            })->max('msl_item_perform');
                            $total_msl_check = $total_msl_check+$customer_max_msl_check;
                        @endphp
                    @endforeach
                    @php
                        if(!in_array($merchandiser->merchandiser_id,$merchatArray)){
                            array_push($merchatArray,$merchandiser->merchandiser_id);
                        }else{
                            continue;
                        }
                    @endphp
                            <tr>
                                @if(count($merchandiser_id) > 0)
                                    <td>{{ $merchandiser->merchandiser_name!='' ? $merchandiser->merchandiser_name:'N/A' }}</td>
                                @endif

                                @if(count($customer_id))
                                    <td>{{ $merchandiser->customer_name!='' ? $merchandiser->customer_name:'N/A' }}</td>
                                @endif
                            
                            <td>{{ $total_msl ?? 0}}</td>
                            <td>{{ $total_msl_check ?? 0}}</td>
                            @php
                                $total_msl = $total_msl==0 ? 1 : $total_msl;
                            @endphp
                            <td>{{ round(($total_msl_check/$total_msl)*100) }}</td>
                        </tr>
                    @endforeach
                 </tbody>
                        </table>
                    </tr>
            </table>
        </body>
    </html>
