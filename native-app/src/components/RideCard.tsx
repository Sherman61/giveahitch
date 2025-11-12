import { FC, useMemo } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { Card } from './Card';
import { RideAcceptButton } from './RideAcceptButton';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { RideSummary } from '@/types/rides';

dayjs.extend(relativeTime);

interface Props {
  ride: RideSummary;
  currentUserId?: number | null;
  onRequireLogin: () => void;
  onRideAccepted: () => void;
}

const statusCopy: Record<string, string> = {
  open: 'Open',
  cancelled: 'Cancelled',
  completed: 'Completed',
  closed: 'Closed',
};

const statusColor: Record<string, string> = {
  open: palette.primary,
  cancelled: palette.danger,
  completed: palette.muted,
  closed: palette.muted,
};

const formatDateLabel = (value?: string | null) => {
  if (!value) return null;
  return dayjs(value).format('MMM D, h:mm A');
};

export const RideCard: FC<Props> = ({ ride, currentUserId, onRequireLogin, onRideAccepted }) => {
  const seatLabel = ride.packageOnly || ride.seats === 0 ? 'Package only' : `${ride.seats} seat${ride.seats === 1 ? '' : 's'}`;
  const startLabel = formatDateLabel(ride.departureTime);
  const endLabel = formatDateLabel(ride.endTime);
  const createdRelative = ride.createdAt ? dayjs(ride.createdAt).fromNow() : null;
  const statusLabel = statusCopy[ride.status] ?? ride.status;
  const statusTextColor = statusColor[ride.status] ?? palette.text;
  const isOwnRide = currentUserId !== null && ride.ownerId !== null && ride.ownerId === currentUserId;

  const contactBlock = useMemo(() => {
    if (ride.contactVisibility?.visible) {
      const rows: string[] = [];
      if (ride.phone) {
        rows.push(`Phone: ${ride.phone}`);
      }
      if (ride.whatsapp) {
        rows.push(`WhatsApp: ${ride.whatsapp}`);
      }
      if (rows.length === 0) {
        rows.push('No contact details provided yet.');
      }
      return rows.map((line) => (
        <Text key={line} style={styles.contactLine}>
          {line}
        </Text>
      ));
    }
    const message = ride.contactNotice ?? 'Contact becomes visible once a ride is accepted.';
    return <Text style={styles.contactNotice}>{message}</Text>;
  }, [ride.contactNotice, ride.contactVisibility?.visible, ride.phone, ride.whatsapp]);

  return (
    <Card>
      <View style={styles.header}>
        <View>
          <View style={styles.typeRow}>
            <Text style={[styles.typePill, ride.type === 'request' && styles.requestPill]}>
              {ride.type === 'offer' ? 'Offer' : 'Request'}
            </Text>
            {isOwnRide && <Text style={styles.badge}>Your ride</Text>}
          </View>
          <Text style={styles.ownerText}>by {ride.ownerName}</Text>
        </View>
        <Text style={[styles.statusPill, { color: statusTextColor, borderColor: statusTextColor }]}>{statusLabel}</Text>
      </View>

      <Text style={styles.route}>{`${ride.origin} -> ${ride.destination}`}</Text>
      <View style={styles.metaRow}>
        <Text style={styles.metaText}>Seats: {seatLabel}</Text>
        {startLabel && <Text style={styles.metaText}>Starts {startLabel}</Text>}
        {endLabel && <Text style={styles.metaText}>Ends {endLabel}</Text>}
      </View>
      {createdRelative && <Text style={styles.metaSubtle}>Posted {createdRelative}</Text>}

      {ride.note ? (
        <View style={styles.noteBox}>
          <Text style={styles.noteLabel}>Note</Text>
          <Text style={styles.noteText}>{ride.note}</Text>
        </View>
      ) : null}

      <View style={styles.contactBox}>
        <Text style={styles.sectionTitle}>Contact</Text>
        {contactBlock}
      </View>

      <RideAcceptButton
        rideId={ride.id}
        ownerId={ride.ownerId}
        status={ride.status}
        currentUserId={currentUserId}
        onAccepted={onRideAccepted}
        onRequireLogin={onRequireLogin}
      />
    </Card>
  );
};

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
    gap: spacing.sm,
  },
  typeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
  },
  typePill: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 999,
    backgroundColor: '#e7f0ff',
    color: palette.primary,
    fontWeight: '600',
    fontSize: 12,
    textTransform: 'uppercase',
  },
  requestPill: {
    backgroundColor: '#d9f5ec',
    color: '#0f8a69',
  },
  badge: {
    fontSize: 12,
    color: palette.primary,
    backgroundColor: '#e3edff',
    paddingHorizontal: spacing.xs,
    paddingVertical: 2,
    borderRadius: 6,
    textTransform: 'uppercase',
  },
  ownerText: {
    color: palette.muted,
    marginTop: 4,
  },
  statusPill: {
    borderWidth: 1,
    borderColor: palette.primary,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: 999,
    fontWeight: '600',
    textTransform: 'uppercase',
    fontSize: 12,
  },
  route: {
    fontSize: 18,
    fontWeight: '700',
    marginBottom: spacing.sm,
    color: palette.text,
  },
  metaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginBottom: spacing.xs,
  },
  metaText: {
    color: palette.muted,
    fontSize: 12,
  },
  metaSubtle: {
    color: palette.muted,
    fontSize: 12,
    marginBottom: spacing.sm,
  },
  noteBox: {
    backgroundColor: '#f5f7ff',
    borderRadius: 8,
    padding: spacing.sm,
    marginBottom: spacing.sm,
  },
  noteLabel: {
    fontSize: 12,
    textTransform: 'uppercase',
    color: palette.muted,
    marginBottom: 4,
  },
  noteText: {
    color: palette.text,
  },
  contactBox: {
    marginBottom: spacing.md,
  },
  sectionTitle: {
    fontWeight: '600',
    marginBottom: 4,
  },
  contactLine: {
    color: palette.text,
  },
  contactNotice: {
    color: palette.muted,
    fontStyle: 'italic',
  },
});
