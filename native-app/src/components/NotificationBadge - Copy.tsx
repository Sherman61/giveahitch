import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { palette } from '@/constants/colors';

interface Props {
  count?: number;
}

export const NotificationBadge: FC<Props> = ({ count = 0 }) => {
  if (count <= 0) return null;
  return (
    <View style={styles.badge}> 
      <Text style={styles.text}>{count}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  badge: {
    minWidth: 20,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 10,
    backgroundColor: palette.danger,
    alignItems: 'center',
  },
  text: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '600',
  },
});
