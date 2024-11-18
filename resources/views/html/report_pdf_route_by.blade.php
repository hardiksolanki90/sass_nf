<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title> {{ $title }} </title>

    <style>
        table {
            border-collapse: collapse;
        }

        header {
            position: fixed;
            left: 0px;
            top: 0px;
            right: 0px;
            /* height: 150px; */
            text-align: center;
        }

        main {
            margin-bottom: 300px;
        }

        .table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1rem;
            background-color: transparent;
        }

        .table-bordered {
            border: 1px solid #3f4040;
        }

        th {
            text-align: inherit;
        }

        .table td,
        .table th {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #3f4040;
        }

        .table-bordered td,
        .table-bordered th {
            border: 1px solid #3f4040;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #3f4040;
        }

        .table-bordered thead td,
        .table-bordered thead th {
            border-bottom-width: 2px;
        }
    </style>
</head>

<body>
    <htmlpageheader name="page-header">
        </div>
        <div class="logo" style="text-align:center">
            <img src="https://devmobiato.nfpc.net/merchandising/public/image/pdf_slogo.png" width="20%" />
        </div>
        <div style="text-align:center">
            <h4 style="font-size:20px;color:#03048c">{{ $title }}</h4>
        </div>
        <div style="display:block">
            @if (isset($w_name))
                <div style="display:inline-block; font-weight: bold;color: #03048c;"> Warehouse: [{{ $w_code }}]
                    {{ $w_name }}</div>
            @endif
            @if (isset($s_code))
                <div style="display:inline-block; font-weight: bold;color: #03048c;"> Salesman: [{{ $s_code }}]
                    {{ $s_name }}</div>
            @endif
            <div style="display:inline-block;float:right;font-weight: bold;color: #03048c;"> Date:
                {{ $date ?? date('Y-m-d') }}
            </div>
            {{-- </htmlpageheader> --}}
            <footer></footer>
            <main>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            @if ($header)
                                @foreach ($header as $h)
                                    <th scope="col">{{ $h }}</th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @if ($rows)
                            @foreach ($rows as $key => $value)
                                <tr>
                                    <td>
                                        {{ $value->item_code }}
                                    </td>
                                    <td>
                                        {{ $value->item_name }}
                                    </td>
                                    <td>
                                        {{ $value->dmd_lower_upc }}
                                    </td>
                                    <td>
                                        {{ $value->p_ref_pack }}
                                    </td>
                                    <td>
                                        {{ $value->p_ref_pc }}
                                    </td>
                                    <td>
                                        {{ $value->dmd_packs }}
                                    </td>
                                    <td>
                                        {{ $value->dmd_pcs }}
                                    </td>
                                    <td>
                                        {{ $value->g_ret_pack }}
                                    </td>
                                    <td>
                                        {{ $value->g_ret_pcs }}
                                    </td>
                                    <td>
                                        {{ $value->dmg_pcs }}
                                    </td>
                                    <td>
                                        {{ $value->exp_pcs }}
                                    </td>
                                    <td>
                                        {{ $value->N_exp_pc }}
                                    </td>
                                    <td>
                                        {{ $value->N_sales_packs }}
                                    </td>
                                    <td>
                                        {{ $value->N_sales_pc }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
                <div style="display:block">
                    <div style="display:inline-block; float:left; width:150px; height:100px;text-align:center">
                        Storekeeper Sign</div>
                    <div style="display:inline-block; float:right;width:150px; height:100px;text-align:center">
                        Salesman Sign</div>
                </div>
            </main>
</body>

</html>
