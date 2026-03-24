import { FC } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  count: number;
  onPress: () => void;
}

export const AlertsBadgeButton: FC<Props> = ({ count, onPress }) => {
  if (count <= 0) {
    return null;
  }

  return (
    <TouchableOpacity style={styles.container} onPress={onPress} accessibilityLabel="View alerts">
      <Text style={styles.label}>Alerts</Text>
      <View style={styles.badge}>
        <Text style={styles.badgeText}>{count}</Text>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    marginLeft: spacing.sm,
    borderRadius: 999,
    backgroundColor: '#edf3f9',
  },
  label: {
    fontSize: 13,
    color: palette.text,
    fontWeight: '700',
  },
  badge: {
    position: 'absolute',
    right: spacing.xs,
    top: spacing.xs,
    backgroundColor: palette.danger,
    borderRadius: 999,
    minWidth: 18,
    height: 18,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  badgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '700',
  },
});
