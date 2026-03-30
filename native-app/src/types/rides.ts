export type RideStatus = 'open' | 'cancelled' | 'completed' | 'closed' | string;

export interface ContactVisibility {
  visible?: boolean;
  reason?: string;
  level?: number;
}

export interface RideSummary {
  id: number;
  ownerId: number | null;
  type: 'offer' | 'request';
  origin: string;
  destination: string;
  departureTime?: string | null;
  endTime?: string | null;
  seats: number;
  packageOnly: boolean;
  note?: string;
  phone?: string;
  whatsapp?: string;
  status: RideStatus;
  createdAt?: string;
  ownerName: string;
  contactVisibility?: ContactVisibility;
  contactNotice?: string;
  matchCounts?: Record<string, number>;
  confirmed?: {
    match_id: number;
    status: string;
  };
}
