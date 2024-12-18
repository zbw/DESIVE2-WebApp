-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Erstellungszeit: 06. Nov 2024 um 10:01
-- Server-Version: 11.4.3-MariaDB-ubu2404
-- PHP-Version: 8.2.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `desive2`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `AppUser`
--

CREATE TABLE `AppUser` (
  `idAppUser` int(11) NOT NULL,
  `idSubject` int(11) NOT NULL,
  `enc_password` varchar(500) NOT NULL,
  `hasSetPassword` tinyint(1) NOT NULL DEFAULT 0,
  `enc_token` varchar(500) DEFAULT NULL,
  `first_login` datetime DEFAULT NULL,
  `last_online` datetime DEFAULT NULL,
  `registration_id` varchar(5000) DEFAULT NULL,
  `pushErrorSince` timestamp NULL DEFAULT NULL,
  `receivesPush` tinyint(1) NOT NULL DEFAULT 0,
  `creation_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `AppVersion`
--

CREATE TABLE `AppVersion` (
  `Version_Id` int(11) NOT NULL,
  `VersionName` double NOT NULL,
  `AppleVersion` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `AppVersion`
--

INSERT INTO `AppVersion` (`Version_Id`, `VersionName`, `AppleVersion`) VALUES
(1, 68, 68);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `CleverpushChannels`
--

CREATE TABLE `CleverpushChannels` (
  `Id_Channel` int(11) NOT NULL,
  `CleverPushChannelId` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `CleverpushChannels`
--

INSERT INTO `CleverpushChannels` (`Id_Channel`, `CleverPushChannelId`) VALUES
(1, 'XXXXXXXXXXXXXXXX');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `DiaryEntry`
--

CREATE TABLE `DiaryEntry` (
  `idDiaryEntry` int(11) NOT NULL,
  `idAppUser` int(11) NOT NULL,
  `entry` varchar(5000) NOT NULL,
  `idUploadAdditionalInformation` int(11) DEFAULT NULL,
  `idUpload` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Incentive_Payment`
--

CREATE TABLE `Incentive_Payment` (
  `paymentID` int(11) NOT NULL,
  `subjectID` int(11) DEFAULT NULL,
  `enc_name` varbinary(255) NOT NULL,
  `enc_email` varbinary(255) NOT NULL,
  `enc_iban` varbinary(255) NOT NULL,
  `paymentListID` int(11) DEFAULT NULL,
  `statusTypeID` int(11) NOT NULL,
  `additionalText` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Incentive_PaymentList`
--

CREATE TABLE `Incentive_PaymentList` (
  `paymentListID` int(11) NOT NULL,
  `paymentConfirmedOn` timestamp NULL DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Incentive_PaymentType`
--

CREATE TABLE `Incentive_PaymentType` (
  `paymentTypeID` int(11) NOT NULL,
  `paymentTypeDescription` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `Incentive_PaymentType`
--

INSERT INTO `Incentive_PaymentType` (`paymentTypeID`, `paymentTypeDescription`) VALUES
(1, 'Interview'),
(2, 'Umfragen'),
(3, 'Uploads'),
(4, 'Experimente'),
(5, 'Bonus');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Incentive_Position`
--

CREATE TABLE `Incentive_Position` (
  `positionID` int(11) NOT NULL,
  `paymentID` int(11) NOT NULL,
  `paymentTypeID` int(11) NOT NULL,
  `amount` decimal(5,2) NOT NULL COMMENT 'Range -999.99 to 999.99',
  `additionalText` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Incentive_StatusType`
--

CREATE TABLE `Incentive_StatusType` (
  `statusTypeID` int(11) NOT NULL,
  `statusTypeDescription` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `Incentive_StatusType`
--

INSERT INTO `Incentive_StatusType` (`statusTypeID`, `statusTypeDescription`) VALUES
(1, 'Buchung vorgemerkt'),
(2, 'Buchung in Auszahlungsliste aufgenommen'),
(3, 'Buchung ausgezahlt'),
(4, 'error'),
(5, 'Buchung vorgemerkt - IBAN FEHLT');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Notifications`
--

CREATE TABLE `Notifications` (
  `idPushNotification` int(11) NOT NULL,
  `idAppUser` int(11) NOT NULL,
  `idPushNotificationType` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `PushNotificationTypes`
--

CREATE TABLE `PushNotificationTypes` (
  `idPushNotificationType` int(11) NOT NULL,
  `Description` varchar(512) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `PushNotificationTypes`
--

INSERT INTO `PushNotificationTypes` (`idPushNotificationType`, `Description`) VALUES
(1, 'Survey 1'),
(2, 'Survey 2'),
(3, 'Survey 3'),
(4, 'Survey 4'),
(5, 'IBAN missing'),
(6, 'Inactivity'),
(7, 'First Login Reminder'),
(8, 'Debriefing and IBAN Reminder');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Subject`
--

CREATE TABLE `Subject` (
  `idSubject` int(11) NOT NULL,
  `SubjectIdentifier` varchar(10) DEFAULT NULL,
  `enc_name` varbinary(255) NOT NULL,
  `enc_mail` varbinary(255) NOT NULL,
  `app` tinyint(1) NOT NULL,
  `interview` tinyint(1) NOT NULL,
  `enc_iban` varbinary(5000) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Survey`
--

CREATE TABLE `Survey` (
  `SurveyID` int(11) NOT NULL,
  `Description` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `Survey`
--

INSERT INTO `Survey` (`SurveyID`, `Description`) VALUES
(1, 'Survey 1'),
(2, 'Survey 2'),
(3, 'Survey 3'),
(4, 'Survey 4');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Upload`
--

CREATE TABLE `Upload` (
  `idUpload` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `uploadType` int(11) NOT NULL,
  `path` varchar(200) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `UploadType`
--

CREATE TABLE `UploadType` (
  `idUploadType` int(11) NOT NULL,
  `TypeDescription` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `UploadType`
--

INSERT INTO `UploadType` (`idUploadType`, `TypeDescription`) VALUES
(1, 'PDF'),
(2, 'Audiofile'),
(3, 'Photo');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `UserAnsweredSurveyQuestion`
--

CREATE TABLE `UserAnsweredSurveyQuestion` (
  `idUserAnswer` int(11) NOT NULL,
  `idUser` int(11) NOT NULL,
  `SurveyID` int(11) NOT NULL,
  `Answer` longtext NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `AppUser`
--
ALTER TABLE `AppUser`
  ADD PRIMARY KEY (`idAppUser`),
  ADD KEY `FK_idSubject` (`idSubject`);

--
-- Indizes für die Tabelle `AppVersion`
--
ALTER TABLE `AppVersion`
  ADD PRIMARY KEY (`Version_Id`);

--
-- Indizes für die Tabelle `CleverpushChannels`
--
ALTER TABLE `CleverpushChannels`
  ADD PRIMARY KEY (`Id_Channel`);

--
-- Indizes für die Tabelle `DiaryEntry`
--
ALTER TABLE `DiaryEntry`
  ADD PRIMARY KEY (`idDiaryEntry`),
  ADD KEY `FK_IDAppUser` (`idAppUser`),
  ADD KEY `FK_idUploadAdditionalInformation` (`idUpload`),
  ADD KEY `FK_AdditionalInfoToUploadID` (`idUploadAdditionalInformation`);

--
-- Indizes für die Tabelle `Incentive_Payment`
--
ALTER TABLE `Incentive_Payment`
  ADD PRIMARY KEY (`paymentID`),
  ADD KEY `FK_idSubject_Subject` (`subjectID`),
  ADD KEY `FK_status_Incentive_StatusType` (`statusTypeID`),
  ADD KEY `FK_paymentListID_Incentive_PaymentList` (`paymentListID`);

--
-- Indizes für die Tabelle `Incentive_PaymentList`
--
ALTER TABLE `Incentive_PaymentList`
  ADD PRIMARY KEY (`paymentListID`);

--
-- Indizes für die Tabelle `Incentive_PaymentType`
--
ALTER TABLE `Incentive_PaymentType`
  ADD PRIMARY KEY (`paymentTypeID`);

--
-- Indizes für die Tabelle `Incentive_Position`
--
ALTER TABLE `Incentive_Position`
  ADD PRIMARY KEY (`positionID`),
  ADD KEY `FK_paymentID_Incentive_Payment` (`paymentID`),
  ADD KEY `FK_paymentTypeID_Incentive_PaymentType` (`paymentTypeID`);

--
-- Indizes für die Tabelle `Incentive_StatusType`
--
ALTER TABLE `Incentive_StatusType`
  ADD PRIMARY KEY (`statusTypeID`);

--
-- Indizes für die Tabelle `Notifications`
--
ALTER TABLE `Notifications`
  ADD PRIMARY KEY (`idPushNotification`),
  ADD KEY `FK_idAppUser_Notifications` (`idAppUser`),
  ADD KEY `FK_pushNotificationType_Notifications` (`idPushNotificationType`);

--
-- Indizes für die Tabelle `PushNotificationTypes`
--
ALTER TABLE `PushNotificationTypes`
  ADD PRIMARY KEY (`idPushNotificationType`);

--
-- Indizes für die Tabelle `Subject`
--
ALTER TABLE `Subject`
  ADD PRIMARY KEY (`idSubject`);

--
-- Indizes für die Tabelle `Survey`
--
ALTER TABLE `Survey`
  ADD PRIMARY KEY (`SurveyID`);

--
-- Indizes für die Tabelle `Upload`
--
ALTER TABLE `Upload`
  ADD PRIMARY KEY (`idUpload`),
  ADD KEY `idUser` (`idUser`),
  ADD KEY `FK_idUpload` (`uploadType`);

--
-- Indizes für die Tabelle `UploadType`
--
ALTER TABLE `UploadType`
  ADD PRIMARY KEY (`idUploadType`);

--
-- Indizes für die Tabelle `UserAnsweredSurveyQuestion`
--
ALTER TABLE `UserAnsweredSurveyQuestion`
  ADD PRIMARY KEY (`idUserAnswer`),
  ADD KEY `F_SubjectIDForAnswer` (`idUser`),
  ADD KEY `FK_Survey_ID` (`SurveyID`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `AppUser`
--
ALTER TABLE `AppUser`
  MODIFY `idAppUser` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `AppVersion`
--
ALTER TABLE `AppVersion`
  MODIFY `Version_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `DiaryEntry`
--
ALTER TABLE `DiaryEntry`
  MODIFY `idDiaryEntry` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `Incentive_Payment`
--
ALTER TABLE `Incentive_Payment`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `Incentive_PaymentList`
--
ALTER TABLE `Incentive_PaymentList`
  MODIFY `paymentListID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `Incentive_PaymentType`
--
ALTER TABLE `Incentive_PaymentType`
  MODIFY `paymentTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `Incentive_Position`
--
ALTER TABLE `Incentive_Position`
  MODIFY `positionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `Incentive_StatusType`
--
ALTER TABLE `Incentive_StatusType`
  MODIFY `statusTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `Notifications`
--
ALTER TABLE `Notifications`
  MODIFY `idPushNotification` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `PushNotificationTypes`
--
ALTER TABLE `PushNotificationTypes`
  MODIFY `idPushNotificationType` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `Subject`
--
ALTER TABLE `Subject`
  MODIFY `idSubject` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `Survey`
--
ALTER TABLE `Survey`
  MODIFY `SurveyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `Upload`
--
ALTER TABLE `Upload`
  MODIFY `idUpload` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `UserAnsweredSurveyQuestion`
--
ALTER TABLE `UserAnsweredSurveyQuestion`
  MODIFY `idUserAnswer` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `AppUser`
--
ALTER TABLE `AppUser`
  ADD CONSTRAINT `FK_idSubject` FOREIGN KEY (`idSubject`) REFERENCES `Subject` (`idSubject`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `DiaryEntry`
--
ALTER TABLE `DiaryEntry`
  ADD CONSTRAINT `FK_AdditionalInfoToUploadID` FOREIGN KEY (`idUploadAdditionalInformation`) REFERENCES `Upload` (`idUpload`),
  ADD CONSTRAINT `FK_EntryToUploadID` FOREIGN KEY (`idUpload`) REFERENCES `Upload` (`idUpload`),
  ADD CONSTRAINT `FK_IDAppUser` FOREIGN KEY (`idAppUser`) REFERENCES `AppUser` (`idAppUser`);

--
-- Constraints der Tabelle `Incentive_Payment`
--
ALTER TABLE `Incentive_Payment`
  ADD CONSTRAINT `FK_idSubject_Subject` FOREIGN KEY (`subjectID`) REFERENCES `Subject` (`idSubject`) ON DELETE SET NULL,
  ADD CONSTRAINT `FK_paymentListID_Incentive_PaymentList` FOREIGN KEY (`paymentListID`) REFERENCES `Incentive_PaymentList` (`paymentListID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_status_Incentive_StatusType` FOREIGN KEY (`statusTypeID`) REFERENCES `Incentive_StatusType` (`statusTypeID`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `Incentive_Position`
--
ALTER TABLE `Incentive_Position`
  ADD CONSTRAINT `FK_paymentID_Incentive_Payment` FOREIGN KEY (`paymentID`) REFERENCES `Incentive_Payment` (`paymentID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_paymentTypeID_Incentive_PaymentType` FOREIGN KEY (`paymentTypeID`) REFERENCES `Incentive_PaymentType` (`paymentTypeID`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `Notifications`
--
ALTER TABLE `Notifications`
  ADD CONSTRAINT `FK_idAppUser_Notifications` FOREIGN KEY (`idAppUser`) REFERENCES `AppUser` (`idAppUser`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_pushNotificationType_Notifications` FOREIGN KEY (`idPushNotificationType`) REFERENCES `PushNotificationTypes` (`idPushNotificationType`);

--
-- Constraints der Tabelle `Upload`
--
ALTER TABLE `Upload`
  ADD CONSTRAINT `FK_UserID` FOREIGN KEY (`idUser`) REFERENCES `AppUser` (`idAppUser`),
  ADD CONSTRAINT `FK_idUpload` FOREIGN KEY (`uploadType`) REFERENCES `UploadType` (`idUploadType`);

--
-- Constraints der Tabelle `UserAnsweredSurveyQuestion`
--
ALTER TABLE `UserAnsweredSurveyQuestion`
  ADD CONSTRAINT `FK_SubjectIDForAnswer` FOREIGN KEY (`idUser`) REFERENCES `AppUser` (`idAppUser`),
  ADD CONSTRAINT `FK_SurveyID` FOREIGN KEY (`SurveyID`) REFERENCES `Survey` (`SurveyID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
