import { FC, useEffect, useRef } from 'react';
import { Animated, Easing, StyleSheet, View } from 'react-native';
import { spacing } from '@/constants/layout';

export const TypingBubble: FC = () => {
  const bubblePulse = useRef(new Animated.Value(0)).current;
  const dotAnimations = useRef([
    new Animated.Value(0.4),
    new Animated.Value(0.4),
    new Animated.Value(0.4),
  ]).current;

  useEffect(() => {
    const bubbleLoop = Animated.loop(
      Animated.sequence([
        Animated.timing(bubblePulse, {
          toValue: 1,
          duration: 1200,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.timing(bubblePulse, {
          toValue: 0,
          duration: 1200,
          easing: Easing.inOut(Easing.ease),
          useNativeDriver: true,
        }),
      ]),
    );

    const dotLoop = Animated.loop(
      Animated.stagger(
        180,
        dotAnimations.map((dot) =>
          Animated.sequence([
            Animated.timing(dot, {
              toValue: 1,
              duration: 320,
              easing: Easing.out(Easing.ease),
              useNativeDriver: true,
            }),
            Animated.timing(dot, {
              toValue: 0.4,
              duration: 320,
              easing: Easing.in(Easing.ease),
              useNativeDriver: true,
            }),
          ]),
        ),
      ),
    );

    bubbleLoop.start();
    dotLoop.start();

    return () => {
      bubbleLoop.stop();
      dotLoop.stop();
    };
  }, [bubblePulse, dotAnimations]);

  return (
    <View style={[styles.messageRow, styles.messageLeft]}>
      <Animated.View
        style={[
          styles.messageBubble,
          styles.typingBubble,
          {
            opacity: bubblePulse.interpolate({
              inputRange: [0, 1],
              outputRange: [0.94, 1],
            }),
            transform: [
              {
                translateY: bubblePulse.interpolate({
                  inputRange: [0, 1],
                  outputRange: [0, -2],
                }),
              },
              {
                scale: bubblePulse.interpolate({
                  inputRange: [0, 1],
                  outputRange: [1, 1.015],
                }),
              },
            ],
          },
        ]}
      >
        <View style={styles.typingDots}>
          {dotAnimations.map((dot, index) => (
            <Animated.View
              key={index}
              style={[
                styles.typingDot,
                {
                  opacity: dot,
                  transform: [
                    {
                      translateY: dot.interpolate({
                        inputRange: [0.4, 1],
                        outputRange: [1.5, -3],
                      }),
                    },
                    {
                      scale: dot.interpolate({
                        inputRange: [0.4, 1],
                        outputRange: [0.92, 1.12],
                      }),
                    },
                  ],
                },
              ]}
            />
          ))}
        </View>
      </Animated.View>
    </View>
  );
};

const styles = StyleSheet.create({
  messageRow: {
    flexDirection: 'row',
  },
  messageLeft: {
    justifyContent: 'flex-start',
  },
  messageBubble: {
    maxWidth: '84%',
    borderRadius: 16,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    gap: 4,
    backgroundColor: '#eef2f6',
  },
  typingBubble: {
    minWidth: 72,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    borderTopLeftRadius: 8,
    shadowColor: '#9cabbc',
    shadowOpacity: 0.16,
    shadowRadius: 8,
    shadowOffset: {
      width: 0,
      height: 3,
    },
    elevation: 1,
  },
  typingDots: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  typingDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
    backgroundColor: '#8fa0b2',
  },
});
