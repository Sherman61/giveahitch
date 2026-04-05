import { FC, useState } from 'react';
import { TouchableOpacity, Text, StyleSheet, ActivityIndicator, Alert } from 'react-native';
import { acceptRide } from '@/api/rides';
import { palette } from '@/constants/colors';

interface Props {
  rideId: number;
  ownerId: number | null;
  status: string;
  currentUserId?: number | null;
  onAccepted: (rideId: number, status: string) => void;
  onRequireLogin: () => void;
}

export const RideAcceptButton: FC<Props> = ({
  rideId,
  ownerId,
  status,
  currentUserId,
  onAccepted,
  onRequireLogin,
}) => {
  const [loading, setLoading] = useState(false);
  const isOwner = ownerId !== null && ownerId === currentUserId;
  const isOpen = status === 'open';

  if (!isOpen || isOwner) {
    return null;
  }

  const handlePress = async () => {
    if (!currentUserId) {
      Alert.alert('Sign in required', 'Log in to accept this ride.', [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Log in', onPress: onRequireLogin },
      ]);
      onRequireLogin();
      return;
    }

    try {
      setLoading(true);
      await acceptRide(rideId);
      Alert.alert('Request sent', 'The ride owner has been notified.');
      onAccepted(rideId, 'pending');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to accept ride';
      Alert.alert('Accept failed', message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <TouchableOpacity style={styles.button} onPress={handlePress} disabled={loading}>
      {loading ? (
        <ActivityIndicator color="#fff" />
      ) : (
        <Text style={styles.label}>Accept ride</Text>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  button: {
    backgroundColor: palette.primary,
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  label: {
    color: '#fff',
    fontWeight: '600',
  },
});
