package com.fds.util

import java.math.BigDecimal

fun toMinor(amount: String): Long =
    (BigDecimal(amount).setScale(2).movePointRight(2)).longValueExact()

fun toMajorString(minor: Long): String =
    BigDecimal(minor).movePointLeft(2).setScale(2).toPlainString()
