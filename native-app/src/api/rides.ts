import { apiClient } from './client';
import { RideSummary } from '@/components/RideCard';

type ServerRide = {
  id: number | string;
  origin: string;
  destination: string;
  departure_time: string;
  driver_name?: string | null;
  status?: RideSummary['status'] | string | null;
};

function mapRide(serverRide: ServerRide): RideSummary {
  const status = (serverRide.status ?? 'scheduled') as RideSummary['status'];
  return {
    id: String(serverRide.id),
    origin: serverRide.origin,
    destination: serverRide.destination,
    departureTime: serverRide.departure_time,
    driverName: serverRide.driver_name ?? 'Unassigned',
    status: ['scheduled', 'completed', 'cancelled', 'awaiting'].includes(status)
      ? status
      : 'scheduled',
  };
}

export async function fetchUpcomingRides() {
  const data = await apiClient.get<ServerRide[]>('/rides.php');
  return data.map(mapRide);
}
