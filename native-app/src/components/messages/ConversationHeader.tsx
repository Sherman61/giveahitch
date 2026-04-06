import { FC } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  activePresenceText: string;
  connectionState: string;
  isActiveUserOnline?: boolean;
  onBack: () => void;
}

export const ConversationHeader: FC<Props> = ({
  activePresenceText,
  connectionState,
  isActiveUserOnline = false,
  onBack,
}) => {
  const isLiveStatus = activePresenceText === 'Online' || activePresenceText === 'Typing...';

  return (
    <View style={styles.sectionHeader}>
      <View style={styles.headerCopy}>
        <View style={styles.titleRow}>
          <Text style={styles.sectionTitle}>Conversation</Text>
          {isActiveUserOnline ? <Text style={styles.livePill}>Active now</Text> : null}
        </View>
        <View style={styles.statusRow}>
          <View
            style={[
              styles.statusDot,
              isLiveStatus ? styles.statusDotOnline : styles.statusDotNeutral,
            ]}
          />
          <Text style={styles.subtitle}>{activePresenceText}</Text>
          <ConnectionBar activePresenceText={activePresenceText} state={connectionState} />
        </View>
      </View>
      <TouchableOpacity onPress={onBack} activeOpacity={0.82}>
        <Text style={styles.link}>Back to inbox</Text>
      </TouchableOpacity>
    </View>
  );
};

const ConnectionBar: FC<{ activePresenceText: string; state: string }> = ({ activePresenceText, state }) => {
  const isPeerConnected = activePresenceText === 'Online' || activePresenceText === 'Typing...';
  const isChecking = activePresenceText === 'Checking status...' || state === 'connecting';
  const hasResolvedLastSeen = activePresenceText.startsWith('Last online ');
  const strength =
    state === 'connected' ? (isPeerConnected ? 3 : isChecking || hasResolvedLastSeen ? 2 : 1) : state === 'connecting' ? 2 : 1;
  const bars = [8, 12, 16];
  const shouldShowLabel = state !== 'connected' || (!isPeerConnected && !hasResolvedLastSeen);
  const connectionLabel =
    state !== 'connected'
      ? state === 'connecting'
        ? 'Checking'
        : 'Unavailable'
      : isPeerConnected
        ? 'Live'
        : hasResolvedLastSeen
          ? 'Seen'
          : isChecking
            ? 'Checking'
            : 'Unavailable';

  return (
    <View style={styles.connectionBarWrapper}>
      <View style={styles.signalBars}>
        {bars.map((height, index) => (
          <View
            key={index}
            style={[
              styles.signalBar,
              { height },
              index < strength ? styles.signalBarFilled : styles.signalBarEmpty,
            ]}
          />
        ))}
      </View>
      {shouldShowLabel ? <Text style={styles.connectionText}>{connectionLabel}</Text> : null}
    </View>
  );
};

const styles = StyleSheet.create({
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: spacing.sm,
  },
  headerCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  livePill: {
    color: '#2e9f6b',
    fontSize: 12,
    fontWeight: '700',
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    flexWrap: 'wrap',
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
  },
  statusDotOnline: {
    backgroundColor: '#2e9f6b',
  },
  statusDotNeutral: {
    backgroundColor: '#d6dde5',
  },
  subtitle: {
    color: palette.muted,
  },
  link: {
    color: palette.primary,
    fontWeight: '600',
  },
  connectionBarWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
  },
  signalBars: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: spacing.xs,
    marginRight: spacing.xs,
  },
  signalBar: {
    width: 5,
    borderRadius: 2,
    backgroundColor: '#e6eefb',
  },
  signalBarFilled: {
    backgroundColor: palette.primary,
  },
  signalBarEmpty: {
    backgroundColor: '#e6eefb',
  },
  connectionText: {
    color: palette.muted,
    fontSize: 12,
  },
});
