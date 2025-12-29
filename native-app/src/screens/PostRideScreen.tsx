import { FC, useState } from 'react';
import {
  View,
  Text,
  TextInput,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import dayjs from 'dayjs';
import { Card } from '@/components/Card';
import { PrimaryButton } from '@/components/PrimaryButton';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { useCreateRide } from '@/hooks/useCreateRide';
import { UserProfile } from '@/types/user';
import { DateTimeField } from '@/components/DateTimeField';
import { useAlerts } from '@/hooks/useAlerts';
import { AlertsBadgeButton } from '@/components/AlertsBadgeButton';

interface Props {
  currentUser: UserProfile | null;
  onRequireLogin: () => void;
  onNavigate: (key: string) => void;
}

export const PostRideScreen: FC<Props> = ({ currentUser, onRequireLogin, onNavigate }) => {
  const [rideType, setRideType] = useState<'offer' | 'request'>('offer');
  const [fromText, setFromText] = useState('');
  const [toText, setToText] = useState('');
  const [startTime, setStartTime] = useState('');
  const [endTime, setEndTime] = useState('');
  const [seats, setSeats] = useState('1');
  const [phone, setPhone] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [notes, setNotes] = useState('');
  const [formError, setFormError] = useState<string | null>(null);
  const { createRideAsync, loading, error, successMessage } = useCreateRide();
  const { unreadCount } = useAlerts(Boolean(currentUser?.id));

  if (!currentUser) {
    return (
      <ScrollView contentContainerStyle={styles.content} style={styles.container}>
        <Card>
          <Text style={styles.title}>Sign in to post a ride</Text>
          <Text style={styles.subtitle}>You need to be logged in to create ride offers or requests.</Text>
          <PrimaryButton label="Log In" onPress={onRequireLogin} />
        </Card>
      </ScrollView>
    );
  }

  const handleSubmit = async () => {
    const trimmedFrom = fromText.trim();
    const trimmedTo = toText.trim();
    const trimmedPhone = phone.trim();
    const trimmedWhatsapp = whatsapp.trim();
    const trimmedNotes = notes.trim();

    if (!trimmedFrom || !trimmedTo) {
      setFormError('Origin and destination are required.');
      return;
    }

    if (!trimmedPhone && !trimmedWhatsapp) {
      setFormError('Provide at least one contact method (phone or WhatsApp).');
      return;
    }

    const sameLocations =
      trimmedFrom.localeCompare(trimmedTo, undefined, { sensitivity: 'accent' }) === 0;
    if (sameLocations) {
      setFormError('Origin and destination must be different.');
      return;
    }

    const validStart = startTime ? !Number.isNaN(Date.parse(startTime)) : true;
    const validEnd = endTime ? !Number.isNaN(Date.parse(endTime)) : true;
    if (!validStart || !validEnd) {
      setFormError('Enter valid dates in YYYY-MM-DD HH:MM format.');
      return;
    }

    if (startTime && endTime) {
      const start = Date.parse(startTime);
      const end = Date.parse(endTime);
      if (!Number.isNaN(start) && !Number.isNaN(end) && end <= start) {
        setFormError('End time must be after start time.');
        return;
      }
    }

    setFormError(null);

    const formatDate = (value: string) =>
      value ? dayjs(value).format('YYYY-MM-DD HH:mm') : null;

    await createRideAsync({
      type: rideType,
      from_text: trimmedFrom,
      to_text: trimmedTo,
      ride_datetime: formatDate(startTime),
      ride_end_datetime: formatDate(endTime),
      seats: Math.max(0, Number(seats) || 0),
      phone: trimmedPhone || undefined,
      whatsapp: trimmedWhatsapp || undefined,
      note: trimmedNotes || undefined,
    });

    setFromText('');
    setToText('');
    setStartTime('');
    setEndTime('');
    setSeats('1');
    setPhone('');
    setWhatsapp('');
    setNotes('');
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={80}
    >
      <ScrollView contentContainerStyle={styles.content} style={styles.container}>
        <Card>
          <View style={styles.headerRow}>
            <View>
              <Text style={styles.title}>Post a Ride</Text>
              <Text style={styles.subtitle}>Share available seats with the GlitchaHitch community.</Text>
            </View>
            <AlertsBadgeButton count={unreadCount} onPress={() => onNavigate('alerts')} />
          </View>

          <Text style={styles.label}>Type</Text>
          <View style={styles.typeRow}>
            {(['offer', 'request'] as const).map((value) => (
              <TouchableOpacity
                key={value}
                style={[styles.typeOption, rideType === value && styles.typeOptionActive]}
                onPress={() => setRideType(value)}
                activeOpacity={0.8}
              >
                <Text style={[styles.typeLabel, rideType === value && styles.typeLabelActive]}>
                  {value === 'offer' ? 'Offer a Ride' : 'Request a Ride'}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
          <TextInput
            style={styles.input}
            placeholder="Seats (0 = package)"
            keyboardType="numeric"
            value={seats}
            onChangeText={setSeats}
          />

          <TextInput
            style={styles.input}
            placeholder="From (e.g. Borough Park, Brooklyn)"
            value={fromText}
            onChangeText={setFromText}
          />
          <TextInput
            style={styles.input}
            placeholder="To (e.g. Monsey, NY)"
            value={toText}
            onChangeText={setToText}
          />
          <View style={styles.row}>
            <View style={styles.column}>
              <DateTimeField
                label="Start (optional)"
                value={startTime}
                placeholder="Pick start time"
                onChange={(iso) => setStartTime(iso)}
              />
            </View>
            <View style={styles.column}>
              <DateTimeField
                label="End (optional)"
                value={endTime}
                placeholder="Pick end time"
                onChange={(iso) => setEndTime(iso)}
              />
            </View>
          </View>
          <TextInput
            style={styles.input}
            placeholder="Phone (+1 718 555 1234)"
            value={phone}
            onChangeText={setPhone}
            keyboardType="phone-pad"
          />
          <TextInput
            style={styles.input}
            placeholder="WhatsApp (+1 347 555 7890)"
            value={whatsapp}
            onChangeText={setWhatsapp}
            keyboardType="phone-pad"
          />
          <Text style={styles.helper}>Provide at least one contact method.</Text>
          <TextInput
            style={[styles.input, styles.multiline]}
            placeholder="Notes (optional)"
            value={notes}
            onChangeText={setNotes}
            multiline
            numberOfLines={3}
          />

          {formError && <Text style={styles.error}>{formError}</Text>}
          {error && <Text style={styles.error}>{error}</Text>}
          {successMessage && <Text style={styles.success}>{successMessage}</Text>}

          <PrimaryButton label={loading ? 'Posting...' : 'Post Ride'} onPress={handleSubmit} />
        </Card>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: palette.background,
  },
  flex: {
    flex: 1,
  },
  content: {
    padding: spacing.lg,
    gap: spacing.md,
    paddingBottom: spacing.xl,
  },
  title: {
    fontSize: 22,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  subtitle: {
    color: palette.muted,
    marginBottom: spacing.md,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  column: {
    flex: 1,
  },
  label: {
    fontSize: 12,
    textTransform: 'uppercase',
    color: palette.muted,
    marginBottom: 4,
  },
  typeRow: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  typeOption: {
    flex: 1,
    paddingVertical: spacing.sm,
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
  },
  typeOptionActive: {
    borderColor: palette.primary,
    backgroundColor: '#e7f0ff',
  },
  typeLabel: {
    color: palette.muted,
    fontWeight: '600',
  },
  typeLabelActive: {
    color: palette.primary,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.sm,
    backgroundColor: palette.surface,
  },
  multiline: {
    height: 100,
    textAlignVertical: 'top',
  },
  error: {
    color: palette.danger,
    marginBottom: spacing.sm,
  },
  success: {
    color: palette.primary,
    marginBottom: spacing.sm,
  },
  helper: {
    fontSize: 12,
    color: palette.muted,
    marginBottom: spacing.sm,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: spacing.md,
    marginBottom: spacing.md,
  },
});
