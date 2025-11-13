import { FC, ReactNode, useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Modal,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { RideSummary } from '@/types/rides';
import { useRideManage } from '@/hooks/useRideManage';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { PrimaryButton } from './PrimaryButton';
import { deleteRide } from '@/api/rides';
import { getSiteBaseUrl } from '@/api/client';

dayjs.extend(relativeTime);

interface Props {
  ride: RideSummary | null;
  visible: boolean;
  onClose: () => void;
  onEdit: (ride: RideSummary) => void;
  onRideUpdated: () => void;
  onRideDeleted: () => void;
  onNavigate?: (key: string) => void;
  refreshSignal: number;
}

const siteUrl = getSiteBaseUrl();

export const RideManageModal: FC<Props> = ({
  ride,
  visible,
  onClose,
  onEdit,
  onRideUpdated,
  onRideDeleted,
  onNavigate,
  refreshSignal,
}) => {
  const rideId = ride?.id ?? null;
  const { details, loading, error, refresh } = useRideManage(rideId, visible);
  const [deleting, setDeleting] = useState(false);

  const handleRefresh = useCallback(() => {
    refresh();
    onRideUpdated();
  }, [refresh, onRideUpdated]);

  const responderList = details?.pending ?? [];
  const responderLabel = useMemo(() => {
    const count = responderList.length;
    if (!count) return 'No responses yet.';
    return count === 1 ? '1 response waiting' : `${count} responses waiting`;
  }, [responderList.length]);

  useEffect(() => {
    if (visible && rideId) {
      handleRefresh();
    }
  }, [visible, rideId, handleRefresh, refreshSignal]);

  if (!ride) {
    return null;
  }

  const openProfile = (userId: number) => {
    Linking.openURL(`${siteUrl}/user.php?id=${userId}`).catch(() => {});
  };

  const openMessages = (matchId: number) => {
    Linking.openURL(`${siteUrl}/messages.php?match=${matchId}`).catch(() => {});
    onNavigate?.('messages');
  };

  const handleDelete = () => {
    if (!ride) return;
    Alert.alert(
      'Delete ride',
      'This will cancel the ride and remove all pending responses.',
      [
        { text: 'Keep ride', style: 'cancel' },
        {
          text: 'Delete ride',
          style: 'destructive',
          onPress: performDelete,
        },
      ],
    );
  };

  const performDelete = async () => {
    if (!ride) return;
    try {
      setDeleting(true);
      await deleteRide(ride.id);
      Alert.alert('Ride deleted', 'We removed the ride and notified responders.');
      onRideDeleted();
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Unable to delete ride.';
      Alert.alert('Delete failed', message);
    } finally {
      setDeleting(false);
    }
  };

  const renderResponder = (responderId: string, body: ReactNode) => (
    <View key={responderId} style={styles.responderCard}>
      {body}
    </View>
  );

  return (
    <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
      <SafeAreaView style={styles.modalRoot}>
        <View style={styles.modalHeader}>
          <Text style={styles.modalTitle}>Manage ride</Text>
          <TouchableOpacity onPress={onClose}>
            <Text style={styles.closeText}>Close</Text>
          </TouchableOpacity>
        </View>
        <ScrollView contentContainerStyle={styles.modalContent}>
          <View style={styles.summaryCard}>
            <Text style={styles.summaryTitle}>
              {ride.origin} {'->'} {ride.destination}
            </Text>
            <Text style={styles.summaryMeta}>
              {ride.type === 'offer' ? 'Offer' : 'Request'} · {ride.status.toUpperCase()}
            </Text>
            <Text style={styles.summaryMeta}>{responderLabel}</Text>
            <PrimaryButton label="Refresh responses" onPress={handleRefresh} variant="secondary" />
          </View>

          {error && <Text style={styles.error}>{error}</Text>}

          {loading && (
            <View style={styles.loadingRow}>
              <ActivityIndicator />
              <Text style={styles.loadingText}>Loading responses…</Text>
            </View>
          )}

          <Text style={styles.sectionTitle}>Responses</Text>
          {!loading && responderList.length === 0 && (
            <Text style={styles.emptyText}>
              Nobody has responded yet. Share your ride link or check back soon.
            </Text>
          )}

          {responderList.map((responder) =>
            renderResponder(String(responder.matchId), (
              <>
                <View style={styles.responderHeader}>
                  <Text style={styles.responderName}>{responder.name ?? 'Community member'}</Text>
                  <Text style={styles.responderStatus}>{responder.status}</Text>
                </View>
                <Text style={styles.responderMeta}>
                  Requested {dayjs(responder.requestedAt).fromNow()}
                </Text>
                {responder.phone && <Text style={styles.contactLine}>Phone: {responder.phone}</Text>}
                {responder.whatsapp && <Text style={styles.contactLine}>WhatsApp: {responder.whatsapp}</Text>}
                {!responder.phone && !responder.whatsapp && responder.contactNotice && (
                  <Text style={styles.notice}>{responder.contactNotice}</Text>
                )}
                <View style={styles.actionRow}>
                  <TouchableOpacity onPress={() => openProfile(responder.userId)}>
                    <Text style={styles.actionLink}>View profile</Text>
                  </TouchableOpacity>
                  <TouchableOpacity onPress={() => openMessages(responder.matchId)}>
                    <Text style={styles.actionLink}>Message</Text>
                  </TouchableOpacity>
                </View>
              </>
            )),
          )}

          {details?.confirmed && (
            <View style={styles.confirmedCard}>
              <Text style={styles.sectionTitle}>Confirmed match</Text>
              <Text style={styles.responderName}>{details.confirmed.name ?? 'Confirmed rider'}</Text>
              <Text style={styles.responderMeta}>Status: {details.confirmed.status}</Text>
              {details.confirmed.phone && <Text style={styles.contactLine}>Phone: {details.confirmed.phone}</Text>}
              {details.confirmed.whatsapp && (
                <Text style={styles.contactLine}>WhatsApp: {details.confirmed.whatsapp}</Text>
              )}
              {!details.confirmed.phone && !details.confirmed.whatsapp && details.confirmed.contactNotice && (
                <Text style={styles.notice}>{details.confirmed.contactNotice}</Text>
              )}
              <TouchableOpacity onPress={() => details.confirmed && openMessages(details.confirmed.matchId)}>
                <Text style={styles.actionLink}>Open conversation</Text>
              </TouchableOpacity>
            </View>
          )}

          <View style={styles.footerActions}>
            <PrimaryButton label="Edit ride" onPress={() => onEdit(ride)} />
            <TouchableOpacity onPress={handleDelete} disabled={deleting}>
              <Text style={[styles.deleteText, deleting && styles.disabledDelete]}>
                {deleting ? 'Deleting…' : 'Delete ride'}
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </SafeAreaView>
    </Modal>
  );
};

const styles = StyleSheet.create({
  modalRoot: {
    flex: 1,
    backgroundColor: palette.background,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
  },
  closeText: {
    color: palette.primary,
    fontWeight: '600',
  },
  modalContent: {
    padding: spacing.lg,
    paddingBottom: spacing.xl,
    gap: spacing.md,
  },
  summaryCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    gap: spacing.xs,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  summaryTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  summaryMeta: {
    color: palette.muted,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  error: {
    color: palette.danger,
  },
  loadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  loadingText: {
    color: palette.muted,
  },
  emptyText: {
    color: palette.muted,
    fontStyle: 'italic',
  },
  responderCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    gap: spacing.xs,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 1,
  },
  responderHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  responderName: {
    fontWeight: '700',
  },
  responderStatus: {
    color: palette.primary,
    fontWeight: '600',
  },
  responderMeta: {
    color: palette.muted,
    fontSize: 12,
  },
  contactLine: {
    color: palette.text,
  },
  notice: {
    color: palette.muted,
    fontStyle: 'italic',
  },
  actionRow: {
    flexDirection: 'row',
    justifyContent: 'flex-start',
    gap: spacing.lg,
    marginTop: spacing.xs,
  },
  actionLink: {
    color: palette.primary,
    fontWeight: '600',
  },
  confirmedCard: {
    backgroundColor: '#f5f7ff',
    borderRadius: 12,
    padding: spacing.md,
    gap: spacing.xs,
  },
  footerActions: {
    gap: spacing.sm,
  },
  deleteText: {
    color: palette.danger,
    fontWeight: '600',
    textAlign: 'center',
  },
  disabledDelete: {
    opacity: 0.6,
  },
});
