import { FC, ReactNode } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { palette } from '@/constants/colors';
import { radius, spacing } from '@/constants/layout';

interface Props {
  title: string;
  subtitle?: string;
  backLabel?: string;
  onBack?: () => void;
  rightAccessory?: ReactNode;
}

export const PageHeader: FC<Props> = ({ title, subtitle, backLabel = 'Back', onBack, rightAccessory }) => {
  return (
    <View style={styles.wrapper}>
      <View style={styles.topRow}>
        {onBack ? (
          <TouchableOpacity style={styles.backButton} onPress={onBack} activeOpacity={0.8}>
            <Text style={styles.backLabel}>{backLabel}</Text>
          </TouchableOpacity>
        ) : (
          <View />
        )}
        {rightAccessory ? <View style={styles.rightAccessory}>{rightAccessory}</View> : <View />}
      </View>
      <Text style={styles.title}>{title}</Text>
      {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
    </View>
  );
};

const styles = StyleSheet.create({
  wrapper: {
    marginBottom: spacing.lg,
    gap: spacing.xs,
  },
  topRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  backButton: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: radius.sm,
    backgroundColor: '#eef2f6',
  },
  backLabel: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '600',
  },
  rightAccessory: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: palette.text,
  },
  subtitle: {
    color: palette.muted,
    fontSize: 15,
    lineHeight: 21,
  },
});
