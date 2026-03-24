import { FC, useMemo } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
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
  matchStatus?: string | null;
  onRequireLogin: () => void;
  onRideAccepted: (rideId: number, status: string) => void;
  onManageRide?: (ride: RideSummary) => void;
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

const matchStatusCopy: Record<string, string> = {
  pending: 'Ride requested',
  accepted: 'Ride accepted',
  confirmed: 'Ride confirmed',
  in_progress: 'Ride in progress',
  completed: 'Ride completed',
  cancelled: 'Request cancelled',
};

const formatDateLabel = (value?: string | null) => {
  if (!value) return null;
  return dayjs(value).format('MMM D, h:mm A');
};

export const RideCard: FC<Props> = ({
  ride,
  currentUserId,
  matchStatus,
  onRequireLogin,
  onRideAccepted,
  onManageRide,
}) => {
  const seatLabel = ride.packageOnly || ride.seats === 0 ? 'Package only' : `${ride.seats} seat${ride.seats === 1 ? '' : 's'}`;
  const startLabel = formatDateLabel(ride.departureTime);
  const endLabel = formatDateLabel(ride.endTime);
  const createdRelative = ride.createdAt ? dayjs(ride.createdAt).fromNow() : null;
  const statusLabel = statusCopy[ride.status] ?? ride.status;
  const statusTextColor = statusColor[ride.status] ?? palette.text;
  const isOwnRide = currentUserId !== null && ride.ownerId !== null && ride.ownerId === currentUserId;
  const responseCount = Object.values(ride.matchCounts ?? {}).reduce((sum, count) => sum + count, 0);
  const canManageRide = isOwnRide && typeof onManageRide === 'function';
  const manageSubtitle =
    responseCount > 0 ? (responseCount === 1 ? '1 response waiting' : `${responseCount} responses waiting`) : 'Manage responses and updates';

  const contactPrivacyLabel = useMemo(() => {
    const level = ride.contactVisibility?.level;
    if (level === 2) return 'Visible to logged-in members';
    if (level === 3) return 'Public while they have an active ride';
    return 'Share after a ride is accepted';
  }, [ride.contactVisibility?.level]);

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
    const message = ride.contactNotice ?? contactPrivacyLabel;
    return (
      <View style={styles.contactNoticeBlock}>
        <Text style={styles.contactNotice}>{message}</Text>
        <Text style={styles.contactPrivacyHint}>{contactPrivacyLabel}</Text>
      </View>
    );
  }, [contactPrivacyLabel, ride.contactNotice, ride.contactVisibility?.visible, ride.phone, ride.whatsapp]);

  return (
    <Card>
      <View style={styles.header}>
        <View style={styles.headerMain}>
          <View style={styles.typeRow}>
            <Text style={[styles.typePill, ride.type === 'request' && styles.requestPill]}>
              {ride.type === 'offer' ? 'Offer' : 'Request'}
            </Text>
            {isOwnRide && <Text style={styles.badge}>Your ride</Text>}
          </View>
          <Text style={styles.route}>{`${ride.origin} to ${ride.destination}`}</Text>
        </View>
        <Text style={[styles.statusPill, { color: statusTextColor, borderColor: statusTextColor }]}>{statusLabel}</Text>
      </View>

      <View style={styles.metaRow}>
        <Text style={styles.metaText}>{seatLabel}</Text>
        {startLabel && <Text style={styles.metaText}>Starts {startLabel}</Text>}
        {endLabel && <Text style={styles.metaText}>Ends {endLabel}</Text>}
      </View>

      <View style={styles.secondaryRow}>
        <Text style={styles.ownerText}>Posted by {ride.ownerName}</Text>
        {createdRelative && <Text style={styles.metaSubtle}>Posted {createdRelative}</Text>}
      </View>

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

      {canManageRide && (
        <View style={styles.manageBox}>
          <Text style={styles.manageText}>{manageSubtitle}</Text>
          <TouchableOpacity style={styles.manageButton} onPress={() => onManageRide?.(ride)}>
            <Text style={styles.manageButtonLabel}>Manage ride</Text>
          </TouchableOpacity>
        </View>
      )}

      {matchStatus ? (
        <View style={styles.matchStatus}>
          <Text style={styles.matchStatusText}>
            {matchStatusCopy[matchStatus] ?? 'Ride offered'}
          </Text>
        </View>
      ) : (
        <RideAcceptButton
          rideId={ride.id}
          ownerId={ride.ownerId}
          status={ride.status}
          currentUserId={currentUserId}
          onRequireLogin={onRequireLogin}
          onAccepted={onRideAccepted}
        />
      )}
    </Card>
  );
};

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  headerMain: {
    flex: 1,
    gap: spacing.sm,
  },
  typeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    flexWrap: 'wrap',
  },
  typePill: {
    backgroundColor: '#e7f0ff',
    color: palette.primary,
    fontWeight: '700',
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  requestPill: {
    backgroundColor: '#fff4e5',
    color: '#b36b00',
  },
  badge: {
    backgroundColor: '#edf2f7',
    color: palette.muted,
    fontWeight: '600',
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
  },
  ownerText: {
    color: palette.muted,
  },
  statusPill: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    fontWeight: '700',
    alignSelf: 'flex-start',
    overflow: 'hidden',
  },
  route: {
    fontSize: 20,
    fontWeight: '700',
    color: palette.text,
  },
  metaRow: {
    gap: spacing.xs,
  },
  metaText: {
    color: palette.text,
  },
  secondaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: spacing.sm,
    flexWrap: 'wrap',
  },
  metaSubtle: {
    color: palette.muted,
    fontSize: 12,
  },
  noteBox: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: spacing.md,
    gap: spacing.xs,
  },
  noteLabel: {
    fontSize: 12,
    textTransform: 'uppercase',
    color: palette.muted,
    letterSpacing: 0.5,
  },
  noteText: {
    color: palette.text,
  },
  contactBox: {
    backgroundColor: '#f8fafc',
    borderRadius: 10,
    padding: spacing.md,
    gap: spacing.xs,
  },
  sectionTitle: {
    fontSize: 13,
    textTransform: 'uppercase',
    color: palette.muted,
    letterSpacing: 0.4,
  },
  contactLine: {
    color: palette.text,
  },
  contactNoticeBlock: {
    gap: 2,
  },
  contactNotice: {
    color: palette.text,
  },
  contactPrivacyHint: {
    color: palette.muted,
    fontSize: 12,
  },
  manageBox: {
    gap: spacing.sm,
  },
  manageText: {
    color: palette.text,
  },
  manageButton: {
    alignSelf: 'flex-start',
    backgroundColor: '#edf3f9',
    borderRadius: 999,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  manageButtonLabel: {
    color: palette.primary,
    fontWeight: '700',
  },
  matchStatus: {
    backgroundColor: '#eef5fb',
    borderRadius: 10,
    padding: spacing.md,
  },
  matchStatusText: {
    color: palette.primary,
    fontWeight: '600',
  },
});
