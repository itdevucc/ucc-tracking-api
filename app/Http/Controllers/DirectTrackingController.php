<?php

namespace App\Http\Controllers;

use App\Http\Resources\TrackingShipmentResource;
use App\Models\Carrier;
use App\Models\TrackingEventMapping;
use App\Models\TrackingShipment;
use App\Tracking\CarrierConnectorManager;
use App\Tracking\DirectTrackingSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Contracts\Cache\LockTimeoutException;

class DirectTrackingController extends Controller
{
    public function show(Request $request, string $booking, CarrierConnectorManager $connectors, DirectTrackingSnapshot $snapshot)
    {

        $input = validator(['booking' => $booking, 'carrier' => $request->query('carrier')], [
            'booking' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._ -]+$/'],
            'carrier' => ['required', 'in:MSCU,HLCU'],
        ])->validate();

        $carrier = Carrier::query()->where('scac', $input['carrier'])->where('is_active', true)->firstOrFail();

        try {

            $response = $connectors->for($carrier->connector)->fetch(new TrackingShipment(['booking_reference' => $input['booking']]));

        } catch (LockTimeoutException $exception) {

            return response()->json(['message' => 'La naviera tiene otras consultas en curso. Espera unos segundos e intenta nuevamente.'], 429);

        } catch (RequestException | ConnectionException $exception) {

            return response()->json(['message' => 'No fue posible consultar la naviera. Intenta nuevamente.', 'carrier_http_status' => $exception instanceof RequestException ? $exception->response->status() : null], 502);

        } catch (\LogicException | \UnexpectedValueException $exception) {

            return response()->json(['message' => 'La configuración o respuesta de la naviera no permite completar la consulta.'], 502);

        }

        $mappings = TrackingEventMapping::query()->where(fn ($query) => $query->whereNull('carrier_id')->orWhere('carrier_id', $carrier->id))->orderByRaw('carrier_id is null')->get();

        $shipment = $snapshot->build($input['booking'], $carrier, $response, $mappings);

        $data = (new TrackingShipmentResource($shipment))->resolve($request);

        $data['tracking_source'] = $carrier->name.' · Consulta directa API '.$response->apiVersion;

        return response()->json(['data' => $data, 'raw' => $response->events, 'carrier_http_status' => $response->httpStatus])->header('Cache-Control', 'no-store, private');

    }
}
