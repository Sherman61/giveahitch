package com.glitchahitch.app.analytics

import android.os.Bundle
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.google.firebase.analytics.FirebaseAnalytics

class FirebaseAnalyticsModule(reactContext: ReactApplicationContext) :
  ReactContextBaseJavaModule(reactContext) {

  private val analytics: FirebaseAnalytics = FirebaseAnalytics.getInstance(reactContext)

  override fun getName(): String = "FirebaseAnalyticsModule"

  @ReactMethod
  fun logScreenView(screenName: String, screenClass: String?, promise: Promise) {
    try {
      val params = Bundle().apply {
        putString(FirebaseAnalytics.Param.SCREEN_NAME, screenName)
        putString(FirebaseAnalytics.Param.SCREEN_CLASS, screenClass ?: screenName)
      }

      analytics.logEvent(FirebaseAnalytics.Event.SCREEN_VIEW, params)
      promise.resolve(null)
    } catch (error: Exception) {
      promise.reject("FIREBASE_ANALYTICS_LOG_SCREEN_ERROR", error)
    }
  }
}
